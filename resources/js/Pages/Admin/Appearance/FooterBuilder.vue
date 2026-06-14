<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'

declare const route: (name: string, params?: unknown) => string

type FooterBlockType = 'about_text' | 'menu_list' | 'contact_info' | 'social_icons' | 'newsletter' | 'custom_html' | 'recent_blog_posts' | 'ai_tool_categories' | 'legal_links' | 'custom_link' | 'image' | 'language_switcher' | 'dark_mode' | 'trust_badges' | 'store_badges' | 'divider' | 'copyright_text' | 'payment_icons' | 'back_to_top'
type ColumnFlex = 'default' | 'column-1' | 'column-2' | 'column-3' | 'column-4'
type ConfigValue = string | number | boolean | null | string[]
type HeadingStyle = 'default' | 'accent' | 'minimal'
type FooterContainerWidth = 'default' | 'full' | 'boxed'

interface FooterBlock {
    id: string
    type: FooterBlockType
    enabled: boolean
    config: Record<string, ConfigValue>
}

interface FooterColumn {
    id: string
    title: string
    subtitle: string
    blocks: FooterBlock[]
}

interface FooterBottomBar {
    copyright_text: string
    menu_slug: string | null
    show_payment_icons: boolean
    payment_icons: string[]
    show_back_to_top: boolean
    border_top: boolean
    padding: number
    bg_color: string
    text_color: string
    column_flex: 'default' | 'left' | 'right'
}

interface FooterBottomColumn {
    id: 'left' | 'right'
    title: string
    blocks: FooterBlock[]
}

interface FooterConfig {
    layout: number
    background: { color: string; image_url: string; overlay_opacity: number }
    custom_css: string
    container_width: FooterContainerWidth
    column_flex: ColumnFlex
    text_color: string
    heading_style: HeadingStyle
    heading_color: string
    heading_font_weight: string
    heading_text_transform: string
    heading_font_size: string
    columns: FooterColumn[]
    bottom_blocks: FooterBlock[]
    bottom_columns: FooterBottomColumn[]
    bottom_bar: FooterBottomBar
}

interface MenuOption {
    id: number
    name: string
    slug: string
}

interface AiCategoryOption {
    id: number
    name: string
    slug: string
    tools_count: number
}

interface PageOption {
    id: number
    title: string
    slug: string
}

interface BlockPaletteItem {
    type: FooterBlockType
    label: string
    description: string
    bottomOnly?: boolean
    config: Record<string, ConfigValue>
}

const props = defineProps<{
    config: FooterConfig
    menus: MenuOption[]
    pages: PageOption[]
    aiCategories: AiCategoryOption[]
}>()

const { t } = useTranslate()

let blockIdSequence = 0
const selectedColumnIndex = ref<number | null>(null)
const selectedBottomColumnIndex = ref<number | null>(null)
const selectedBlockIndex = ref<number | null>(null)
const selectedZone = ref<'columns' | 'bottom'>('columns')
const isDragging = ref(false)
const showImportModal = ref(false)
const importJsonText = ref('')
const showSectionSettings = ref(false)
const showAddElementModal = ref(false)
const showBottomSettings = ref(false)
const showRemoveBlockConfirm = ref(false)
const addElementTarget = ref<{ section: 'columns' | 'bottom'; columnIndex?: number; bottomColumnIndex?: number } | null>(null)

const openAddElementModal = (section: 'columns' | 'bottom', columnIndex?: number, bottomColumnIndex?: number) => {
    addElementTarget.value = { section, columnIndex, bottomColumnIndex }
    showAddElementModal.value = true
}

const addElement = (block: BlockPaletteItem) => {
    if (!addElementTarget.value) return
    const target = addElementTarget.value
    const newBlock: FooterBlock = {
        id: createId(block.type),
        type: block.type,
        enabled: true,
        config: JSON.parse(JSON.stringify(block.config)) as Record<string, ConfigValue>,
    }
    if (target.section === 'bottom' && target.bottomColumnIndex !== undefined) {
        form.bottom_columns[target.bottomColumnIndex].blocks.push(newBlock)
    } else if (target.columnIndex !== undefined) {
        form.columns[target.columnIndex].blocks.push(newBlock)
    }
    showAddElementModal.value = false
    addElementTarget.value = null
}

const addElementTargetLabel = computed(() => {
    if (!addElementTarget.value) return ''
    const tgt = addElementTarget.value
    if (tgt.section === 'bottom') {
        return tgt.bottomColumnIndex === 0 ? t('Bottom Left') : t('Bottom Right')
    }
    return t('Column :count', { count: (tgt.columnIndex ?? 0) + 1 })
})

const createId = (type: string) => {
    blockIdSequence += 1

    return `${type}_${Date.now().toString(36)}_${blockIdSequence.toString(36)}_${Math.random().toString(36).slice(2, 7)}`
}

const defaultBottomBar = (bottomBar?: Partial<FooterBottomBar>): FooterBottomBar => ({
    copyright_text: bottomBar?.copyright_text ?? '',
    menu_slug: bottomBar?.menu_slug ?? null,
    show_payment_icons: bottomBar?.show_payment_icons ?? true,
    payment_icons: bottomBar?.payment_icons ?? [],
    show_back_to_top: bottomBar?.show_back_to_top ?? true,
    border_top: bottomBar?.border_top ?? true,
    padding: Number(bottomBar?.padding ?? 32),
    bg_color: bottomBar?.bg_color ?? '',
    text_color: bottomBar?.text_color ?? '',
    column_flex: bottomBar?.column_flex ?? 'default',
})

const normalizeConfig = (config: FooterConfig): FooterConfig => {
    const cloned = JSON.parse(JSON.stringify(config)) as FooterConfig
    cloned.layout = Math.max(1, Math.min(4, Number(cloned.layout || 4)))
    cloned.background = {
        color: cloned.background?.color ?? '',
        image_url: cloned.background?.image_url ?? '',
        overlay_opacity: Number(cloned.background?.overlay_opacity ?? 0),
    }
    cloned.custom_css = cloned.custom_css ?? ''
    cloned.container_width = cloned.container_width ?? 'default'
    cloned.column_flex = cloned.column_flex ?? 'default'
    cloned.text_color = cloned.text_color ?? ''
    cloned.heading_style = cloned.heading_style ?? 'default'
    cloned.heading_color = cloned.heading_color ?? ''
    cloned.heading_font_weight = cloned.heading_font_weight ?? '700'
    cloned.heading_text_transform = cloned.heading_text_transform ?? 'uppercase'
    cloned.heading_font_size = cloned.heading_font_size ?? '12px'
    cloned.bottom_blocks = cloned.bottom_blocks ?? []
    const bottomOnlyTypes = ['copyright_text', 'payment_icons', 'back_to_top', 'social_icons', 'legal_links', 'custom_html', 'divider']
    const legacyBottomBlocks = cloned.bottom_blocks
    const bottomColumns = cloned.bottom_columns ?? []
    const normalizedBottomColumns: FooterBottomColumn[] = [
        {
            id: 'left',
            title: t('Left Column'),
            blocks: (bottomColumns[0]?.blocks ?? legacyBottomBlocks).filter((block: FooterBlock) => bottomOnlyTypes.includes(block.type)),
        },
        {
            id: 'right',
            title: t('Right Column'),
            blocks: bottomColumns[1]?.blocks ?? [],
        },
    ]

    cloned.bottom_columns = normalizedBottomColumns.map((column) => ({
        ...column,
        blocks: (column.blocks ?? []).map((block) => ({
            ...block,
            enabled: block.enabled ?? true,
            config: block.config ?? {},
        })),
    }))
    cloned.columns = (cloned.columns ?? []).slice(0, cloned.layout).map((column, index) => ({
        id: column.id || `footer_column_${index + 1}`,
        title: column.title ?? '',
        subtitle: column.subtitle ?? '',
        blocks: (column.blocks ?? []).map((block) => ({
            ...block,
            enabled: block.enabled ?? true,
            config: block.config ?? {},
        })),
    }))

    while (cloned.columns.length < cloned.layout) {
        cloned.columns.push({
            id: `footer_column_${cloned.columns.length + 1}`,
            title: '',
            subtitle: '',
            blocks: [],
        })
    }

    return cloned
}

const form = useForm<FooterConfig>(normalizeConfig(props.config))

const availableBlocks: BlockPaletteItem[] = [
    { type: 'about_text', label: 'About Text', description: 'Site default logo and short brand description.', config: { description: '', show_social_icon: false } },
    { type: 'menu_list', label: 'Menu List', description: 'Render links from a saved menu.', config: { title: t('Quick Links'), menu_slug: '' } },
    { type: 'contact_info', label: 'Contact Info', description: 'Address, phone, email, and supporting details.', config: { title: t('Contact Us'), details: '', address: '', phone: '', email: '' } },
    { type: 'social_icons', label: 'Social Icons', description: 'Show configured social follow buttons.', config: { title: t('Follow Us'), display_mode: 'icons' } },
    { type: 'newsletter', label: 'Newsletter Form', description: 'Embed the public newsletter subscription form.', config: { title: t('Subscribe'), description: t('Get the latest updates.') } },
    { type: 'custom_html', label: 'Custom HTML', description: 'Sanitized trusted footer markup.', config: { title: '', content: '' } },
    { type: 'recent_blog_posts', label: 'Recent Blog Posts', description: 'Show latest published posts.', config: { title: t('Latest Posts'), count: 3 } },
    { type: 'ai_tool_categories', label: 'AI Tool Categories', description: 'List active AI tool categories.', config: { title: t('AI Tools'), count: 6 } },
    { type: 'custom_link', label: 'Custom Link', description: 'Single custom footer link with access and target rules.', config: { label: t('Learn More'), link_type: 'page', page_slug: '', tool_category_slug: '', custom_url: '', target: '_self', access: 'all', display_mode: 'vertical' } },
    { type: 'image', label: 'Image', description: 'Optional label with linked uploaded image.', config: { title: '', image_url: '', link: '', width: 120, height: 40, target: '_self' } },
    { type: 'language_switcher', label: 'Language Switcher', description: 'Locale selector slot.', config: { title: '' } },
    { type: 'dark_mode', label: 'Dark Mode Toggle', description: 'Theme toggle slot.', config: { title: '' } },
    { type: 'divider', label: 'Divider / Spacer', description: 'Visual divider or spacing block.', config: { spacing: 24, color: '' } },
    { type: 'copyright_text', label: 'Copyright Text', description: 'Copyright text with {year} support.', bottomOnly: true, config: { text: form.bottom_bar.copyright_text || t('© {year} All rights reserved.') } },
    { type: 'payment_icons', label: 'Payment Icons', description: 'Uploaded payment method images.', bottomOnly: true, config: { icons: form.bottom_bar.payment_icons.length ? [...form.bottom_bar.payment_icons] : [] } },
    { type: 'back_to_top', label: 'Back to Top', description: 'Scroll-to-top control.', bottomOnly: true, config: { label: t('Back to top'), icon: 'ti ti-arrow-up', bg_color: '', text_color: '', shape: 'rounded' } },
]

const containerWidthOptions: Array<{ value: FooterContainerWidth; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'full', label: 'Full Width' },
    { value: 'boxed', label: 'Boxed (1080px)' },
]

const headingStyleOptions: Array<{ value: HeadingStyle; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'accent', label: 'Accent' },
    { value: 'minimal', label: 'Minimal' },
]

const headingWeightOptions = [
    { value: '400', label: 'Normal' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semi Bold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extra Bold' },
    { value: '900', label: 'Black' },
]

const headingTransformOptions = [
    { value: 'none', label: 'None' },
    { value: 'uppercase', label: 'UPPERCASE' },
    { value: 'capitalize', label: 'Capitalize' },
]

const columnFlexOptions: Array<{ value: ColumnFlex; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'column-1', label: 'Column 1' },
    { value: 'column-2', label: 'Column 2' },
    { value: 'column-3', label: 'Column 3' },
    { value: 'column-4', label: 'Column 4' },
]

const bottomColumnFlexOptions: Array<{ value: 'default' | 'left' | 'right'; label: string }> = [
    { value: 'default', label: 'Default (Auto)' },
    { value: 'left', label: 'Left Column' },
    { value: 'right', label: 'Right Column' },
]

const socialDisplayModeOptions = [
    { value: 'icons', label: 'Icons Only' },
    { value: 'labels', label: 'Labels Only' },
    { value: 'stacked', label: 'Icons + Labels' },
]

const customLinkTypeOptions = [
    { value: 'page', label: 'Existing Page' },
    { value: 'tool_category', label: 'Tool Category' },
    { value: 'custom', label: 'Custom Link' },
]

const customLinkTargetOptions = [
    { value: '_self', label: 'Same Tab' },
    { value: '_blank', label: 'New Tab' },
]

const customLinkAccessOptions = [
    { value: 'all', label: 'All Users' },
    { value: 'logged_in', label: 'Logged In' },
    { value: 'free', label: 'Free Users' },
]

const customLinkDisplayModeOptions = [
    { value: 'vertical', label: 'Vertical' },
    { value: 'horizontal', label: 'Horizontal' },
]

const backToTopShapeOptions = [
    { value: 'square', label: 'Square' },
    { value: 'rounded', label: 'Rounded' },
    { value: 'pill', label: 'Pill' },
    { value: 'circle', label: 'Circle' },
]

const blockDragOptions = {
    group: { name: 'footer-blocks', pull: true as const, put: true },
    handle: '.footer-block-drag-handle',
    animation: 180,
    ghostClass: 'footer-block-ghost',
    chosenClass: 'footer-block-chosen',
}

const bottomOnlyTypes = ['copyright_text', 'payment_icons', 'back_to_top', 'social_icons', 'legal_links', 'custom_html', 'divider']

const bottomDragOptions = {
    group: { name: 'footer-blocks', pull: true as const, put: (to: any, from: any, el: any) => {
        const type = el?.dataset?.type || el?.getAttribute?.('data-type') || ''
        return bottomOnlyTypes.includes(type)
    }},
    handle: '.footer-block-drag-handle',
    animation: 180,
    ghostClass: 'footer-block-ghost',
    chosenClass: 'footer-block-chosen',
}

const columnDragOptions = {
    group: 'footer-columns',
    handle: '.footer-column-drag-handle',
    animation: 180,
    ghostClass: 'footer-column-ghost',
}

const selectedBlock = computed(() => {
    if (selectedZone.value === 'bottom') {
        return selectedBottomColumnIndex.value === null || selectedBlockIndex.value === null
            ? null
            : form.bottom_columns[selectedBottomColumnIndex.value]?.blocks[selectedBlockIndex.value] ?? null
    }

    if (selectedColumnIndex.value === null || selectedBlockIndex.value === null) {
        return null
    }

    return form.columns[selectedColumnIndex.value]?.blocks[selectedBlockIndex.value] ?? null
})

const layoutGridClass = computed(() => {
    if (form.layout === 1) return 'grid-cols-1'
    if (form.layout === 2) return 'grid-cols-2'
    if (form.layout === 3) return 'grid-cols-3'
    return 'grid-cols-4'
})

const blockLabel = (type: FooterBlockType) => availableBlocks.find((block) => block.type === type)?.label ?? type
const blockDescription = (type: FooterBlockType) => availableBlocks.find((block) => block.type === type)?.description ?? ''

const setLayout = (layout: number) => {
    form.layout = layout

    while (form.columns.length < layout) {
        form.columns.push({
            id: `footer_column_${form.columns.length + 1}`,
            title: '',
            subtitle: '',
            blocks: [],
        })
    }

    form.columns = form.columns.slice(0, layout).map((column) => ({
        ...column,
        title: column.title ?? '',
        subtitle: column.subtitle ?? '',
    }))
}

watch(() => form.layout, (layout) => setLayout(Number(layout)))

const openSettings = (zone: 'columns' | 'bottom', blockIndex: number, columnIndex: number | null = null, bottomColumnIndex: number | null = null) => {
    selectedZone.value = zone
    selectedBlockIndex.value = blockIndex
    selectedColumnIndex.value = columnIndex
    selectedBottomColumnIndex.value = bottomColumnIndex
}

const closeSettings = () => {
    showRemoveBlockConfirm.value = false
    selectedBlockIndex.value = null
    selectedColumnIndex.value = null
    selectedBottomColumnIndex.value = null
}

const removeBlock = () => {
    if (selectedBlockIndex.value === null) return

    if (selectedZone.value === 'bottom') {
        if (selectedBottomColumnIndex.value !== null) {
            form.bottom_columns[selectedBottomColumnIndex.value].blocks.splice(selectedBlockIndex.value, 1)
        }
    } else if (selectedColumnIndex.value !== null) {
        form.columns[selectedColumnIndex.value].blocks.splice(selectedBlockIndex.value, 1)
    }

    closeSettings()
}

const duplicateSelectedBlock = () => {
    if (!selectedBlock.value || selectedBlockIndex.value === null) return

    const duplicate: FooterBlock = {
        ...JSON.parse(JSON.stringify(selectedBlock.value)) as FooterBlock,
        id: createId(selectedBlock.value.type),
    }

    if (selectedZone.value === 'bottom') {
        if (selectedBottomColumnIndex.value !== null) {
            form.bottom_columns[selectedBottomColumnIndex.value].blocks.splice(selectedBlockIndex.value + 1, 0, duplicate)
            selectedBlockIndex.value += 1
        }
    } else if (selectedColumnIndex.value !== null) {
        form.columns[selectedColumnIndex.value].blocks.splice(selectedBlockIndex.value + 1, 0, duplicate)
        selectedBlockIndex.value += 1
    }
}

const moveSelectedBlock = (direction: -1 | 1) => {
    if (selectedBlockIndex.value === null) return

    const blocks = selectedZone.value === 'bottom'
        ? selectedBottomColumnIndex.value === null
            ? []
            : form.bottom_columns[selectedBottomColumnIndex.value].blocks
        : selectedColumnIndex.value === null
            ? []
            : form.columns[selectedColumnIndex.value].blocks

    const target = selectedBlockIndex.value + direction
    if (target < 0 || target >= blocks.length) return

    const [block] = blocks.splice(selectedBlockIndex.value, 1)
    blocks.splice(target, 0, block)
    selectedBlockIndex.value = target
}

const moveColumn = (columnIndex: number, direction: -1 | 1) => {
    const target = columnIndex + direction
    if (target < 0 || target >= form.columns.length) return

    const [column] = form.columns.splice(columnIndex, 1)
    form.columns.splice(target, 0, column)
}

const submit = () => {
    form.bottom_blocks = form.bottom_columns.flatMap((column) => column.blocks)

    form.post(route('admin.footer.update'), {
        preserveScroll: true,
    })
}

const exportConfig = () => {
    const data = JSON.parse(JSON.stringify(form.data()))
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'footer-config.json'
    a.click()
    URL.revokeObjectURL(url)
}

const handleBackgroundUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return

    const payload = new FormData()
    payload.append('file', input.files[0])
    payload.append('directory', 'footer-backgrounds')

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

    try {
        const response = await fetch('/admin/appearance/header/upload-logo', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            body: payload,
        })

        const result = await response.json()

        if (result.url) {
            form.background.image_url = String(result.url)
        }
    } catch (error) {
        console.error('Footer background upload failed:', error)
    } finally {
        input.value = ''
    }
}

const handlePaymentIconUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length || !selectedBlock.value || selectedBlock.value.type !== 'payment_icons') return

    const icons = Array.isArray(selectedBlock.value.config.icons) ? selectedBlock.value.config.icons : []

    for (const file of Array.from(input.files)) {
        const payload = new FormData()
        payload.append('file', file)
        payload.append('directory', 'footer-payments')

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
        const response = await fetch('/admin/appearance/header/upload-logo', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            body: payload,
        })

        const result = await response.json()
        if (result.url) {
            icons.push(String(result.url))
        }
    }

    selectedBlock.value.config.icons = icons
    input.value = ''
}

const removePaymentIconImage = (index: number) => {
    if (!selectedBlock.value || selectedBlock.value.type !== 'payment_icons' || !Array.isArray(selectedBlock.value.config.icons)) return
    selectedBlock.value.config.icons.splice(index, 1)
}

const handleFooterImageUpload = async (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files?.length || !selectedBlock.value || selectedBlock.value.type !== 'image') return

    const payload = new FormData()
    payload.append('file', input.files[0])
    payload.append('directory', 'footer-images')

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    const response = await fetch('/admin/appearance/header/upload-logo', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            Accept: 'application/json',
        },
        body: payload,
    })

    const result = await response.json()
    if (result.url) {
        selectedBlock.value.config.image_url = String(result.url)
    }

    input.value = ''
}

const clearBackgroundImage = () => {
    form.background.image_url = ''
}

const importConfig = () => {
    try {
        const imported = JSON.parse(importJsonText.value)
        const merged = JSON.parse(JSON.stringify(form.data())) as any
        if (imported.columns) merged.columns = imported.columns
        if (imported.bottom_columns) merged.bottom_columns = imported.bottom_columns
        if (imported.bottom_bar) merged.bottom_bar = imported.bottom_bar
        if (imported.background) merged.background = imported.background
        if (imported.custom_css !== undefined) merged.custom_css = imported.custom_css
        if (imported.column_flex !== undefined) merged.column_flex = imported.column_flex
        if (imported.layout) merged.layout = imported.layout
        Object.assign(form, normalizeConfig(merged as FooterConfig))
        showImportModal.value = false
        importJsonText.value = ''
    } catch {
        alert(t('Invalid JSON format.'))
    }
}

const resetFooter = () => {
    form.reset()
    window.location.reload()
}
</script>

<template>
    <Head :title="t('Footer Builder')" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Footer Builder') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Drag footer widgets into columns and bottom bar zones.') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Tooltip :content="t('Export JSON')">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Export JSON')" @click="exportConfig">
                            <i class="ti ti-file-export text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Import JSON')">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Import JSON')" @click="showImportModal = true">
                            <i class="ti ti-file-import text-base"></i>
                        </button>
                    </Tooltip>
                    <button type="button" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60" @click="submit">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z" /></svg>
                        <i v-else class="ti ti-device-floppy text-base"></i>
                        {{ form.processing ? t('Saving...') : t('Save Changes') }}
                    </button>
                </div>
            </div>

            <main class="min-w-0 space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="mb-4 flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Main Footer Grid') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Drag columns left or right, then drop widgets inside each column.') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Settings')" @click="showSectionSettings = true">
                                <i class="ti ti-settings text-base"></i>
                                {{ t('Settings') }}
                            </button>
                        </div>
                    </div>

                    <VueDraggable
                        v-model="form.columns"
                        class="grid gap-4"
                        :class="layoutGridClass"
                        v-bind="columnDragOptions"
                    >
                            <section v-for="(column, columnIndex) in form.columns" :key="column.id" class="min-h-80 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-950">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <button type="button" class="footer-column-drag-handle inline-flex cursor-grab items-center gap-2 rounded-lg px-2 py-1 text-xs font-bold uppercase tracking-wide text-gray-500 hover:bg-white hover:text-primary-700 active:cursor-grabbing dark:hover:bg-surface-900">
                                        <i class="ti ti-grip-vertical text-base"></i>
                                        {{ t('Column :count', { count: columnIndex + 1 }) }}
                                    </button>
                                    <div class="flex items-center gap-1">
                                        <Tooltip :content="t('Move column left')">
                                            <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-white hover:text-primary-600 disabled:opacity-30 dark:hover:bg-surface-900" :disabled="columnIndex === 0" :aria-label="t('Move column left')" @click="moveColumn(Number(columnIndex), -1)">
                                                <i class="ti ti-chevron-left text-base"></i>
                                            </button>
                                        </Tooltip>
                                        <Tooltip :content="t('Move column right')">
                                            <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-white hover:text-primary-600 disabled:opacity-30 dark:hover:bg-surface-900" :disabled="columnIndex === form.columns.length - 1" :aria-label="t('Move column right')" @click="moveColumn(Number(columnIndex), 1)">
                                                <i class="ti ti-chevron-right text-base"></i>
                                            </button>
                                        </Tooltip>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-600 px-2 py-0.75 text-xs text-white transition hover:bg-gray-700 dark:bg-gray-800" @click="openAddElementModal('columns', Number(columnIndex))">
                                            <i class="ti ti-plus text-xs"></i>
                                            {{ t('Add') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 grid gap-2">
                                    <input v-model="column.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200" :placeholder="t('Column heading')">
                                    <input v-model="column.subtitle" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200" :placeholder="t('Column sub heading')">
                                </div>

                                <VueDraggable
                                    v-model="column.blocks"
                                    class="min-h-56 space-y-3 rounded-xl border border-dashed border-gray-200 bg-white/70 p-2 dark:border-surface-700 dark:bg-surface-900/50"
                                    v-bind="blockDragOptions"
                                    @start="isDragging = true"
                                    @end="isDragging = false"
                                >
                                    <article v-for="(block, blockIndex) in column.blocks" :key="block.id" :data-type="block.type" class="rounded-xl border p-3 transition hover:shadow-md" :class="block.enabled ? 'border-primary-200 bg-primary-50/60 dark:border-primary-900/50 dark:bg-primary-900/10' : 'border-gray-200 bg-gray-100 opacity-75 dark:border-surface-700 dark:bg-surface-800'">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <Tooltip :content="t('Drag block')">
                                                    <button type="button" class="footer-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                                        <i class="ti ti-grip-vertical text-base"></i>
                                                    </button>
                                                </Tooltip>
                                                <div class="min-w-0">
                                                    <div class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ t(blockLabel(block.type)) }}</div>
                                                    <div class="truncate text-[11px] text-gray-400">{{ t(blockDescription(block.type)) }}</div>
                                                </div>
                                            </div>
                                            <Tooltip :content="t('Settings')">
                                                <button type="button" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" :aria-label="t('Settings')" @click="openSettings('columns', Number(blockIndex), Number(columnIndex))">
                                                    <i class="ti ti-settings text-base"></i>
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </article>
                                </VueDraggable>

                                <p v-if="column.blocks.length === 0" class="mt-3 text-center text-xs text-gray-400">{{ t('Drop footer widgets here.') }}</p>
                            </section>
                        </VueDraggable>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-surface-800">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Bottom Bar') }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ t('Copyright, payment, menu, social, and back-to-top blocks.') }}</p>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" @click="showBottomSettings = true">
                                <i class="ti ti-settings text-base"></i>
                                {{ t('Settings') }}
                            </button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <section v-for="(bottomColumn, bottomColumnIndex) in form.bottom_columns" :key="bottomColumn.id" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-950">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ t(bottomColumn.title) }}</h4>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-gray-400 dark:bg-surface-900">{{ bottomColumn.blocks.length }}</span>
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-gray-600 px-2 py-0.75 text-xs text-white transition hover:bg-gray-700 dark:bg-gray-800" @click="openAddElementModal('bottom', undefined, Number(bottomColumnIndex))">
                                            <i class="ti ti-plus text-sm"></i>
                                            {{ t('Add') }}
                                        </button>
                                    </div>
                                </div>
                                <VueDraggable v-model="bottomColumn.blocks" class="flex min-h-24 flex-col gap-3" v-bind="bottomDragOptions">
                                    <article v-for="(block, blockIndex) in bottomColumn.blocks" :key="block.id" :data-type="block.type" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 dark:border-primary-900/50 dark:bg-primary-900/10">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <button type="button" class="footer-block-drag-handle cursor-grab text-gray-400 hover:text-primary-600" :aria-label="t('Drag block')">
                                                <i class="ti ti-grip-vertical text-base"></i>
                                            </button>
                                            <span class="truncate text-xs font-bold text-gray-800 dark:text-gray-100">{{ t(blockLabel(block.type)) }}</span>
                                        </div>
                                        <Tooltip :content="t('Settings')">
                                            <button type="button" class="rounded-lg p-2 text-primary-700 hover:bg-white dark:text-primary-300 dark:hover:bg-surface-900" :aria-label="t('Settings')" @click="openSettings('bottom', Number(blockIndex), null, Number(bottomColumnIndex))">
                                                <i class="ti ti-settings text-base"></i>
                                            </button>
                                        </Tooltip>
                                    </article>
                                </VueDraggable>
                            </section>
                        </div>
                    </section>
                </main>
            </div>

        <Teleport to="body">
            <div v-if="selectedBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="closeSettings">
                <section class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t(blockLabel(selectedBlock.type)) }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t(blockDescription(selectedBlock.type)) }}</p>
                        </div>
                        <Tooltip :content="t('Close')">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="closeSettings">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </Tooltip>
                    </div>

                    <div class="space-y-5 overflow-y-auto px-6 py-5">
                        <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <div>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Enabled') }}</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Hide this block without removing its configuration.') }}</span>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="selectedBlock.enabled"
                                class="relative inline-flex h-6 w-11 rounded-full transition"
                                :class="selectedBlock.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                @click="selectedBlock.enabled = !selectedBlock.enabled"
                            >
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="selectedBlock.enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>
                        </div>

                        <label v-if="selectedBlock.config.title !== undefined" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            {{ t('Block Title') }}
                            <input v-model="selectedBlock.config.title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Enter block title')">
                        </label>

                        <template v-if="selectedBlock.type === 'about_text'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Description') }}
                                <textarea v-model="selectedBlock.config.description" rows="4" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Add a short brand summary for the footer.')"></textarea>
                            </label>
                            <button type="button" class="mt-4 flex w-full items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left dark:border-surface-700 dark:bg-surface-900" @click="selectedBlock.config.show_social_icon = !Boolean(selectedBlock.config.show_social_icon)">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Show Social Icon') }}</span>
                                    <span class="text-xs text-gray-500">{{ t('Show social follow icons after the about text.') }}</span>
                                </span>
                                <span class="relative inline-flex h-6 w-11 rounded-full transition-colors" :class="Boolean(selectedBlock.config.show_social_icon) ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'">
                                    <span class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="Boolean(selectedBlock.config.show_social_icon) ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
                                </span>
                            </button>
                        </template>

                        <template v-if="selectedBlock.type === 'menu_list'">
                            <AppSelect
                                v-model="selectedBlock.config.menu_slug"
                                :label="t('Select Menu')"
                                :options="[{ label: t('Select a menu'), value: '' }, ...menus.map((menu) => ({ label: menu.name, value: menu.slug }))]"
                            />
                        </template>

                        <template v-if="selectedBlock.type === 'custom_link'">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Label') }}
                                    <input v-model="selectedBlock.config.label" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Learn More')">
                                </label>
                                <AppSelect
                                    v-model="selectedBlock.config.link_type"
                                    :label="t('Link Type')"
                                    :options="customLinkTypeOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <AppSelect
                                    v-model="selectedBlock.config.target"
                                    :label="t('Link Target')"
                                    :options="customLinkTargetOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <AppSelect
                                    v-if="selectedBlock.config.link_type === 'page'"
                                    v-model="selectedBlock.config.page_slug"
                                    :label="t('Existing Page')"
                                    :options="[{ label: t('Select a page'), value: '' }, ...pages.map((page) => ({ label: page.title, value: page.slug }))]"
                                />
                                <AppSelect
                                    v-if="selectedBlock.config.link_type === 'tool_category'"
                                    v-model="selectedBlock.config.tool_category_slug"
                                    :label="t('Tool Category')"
                                    :options="[{ label: t('Select a category'), value: '' }, ...aiCategories.map((category) => ({ label: category.name, value: category.slug }))]"
                                />
                                <label v-if="selectedBlock.config.link_type === 'custom'" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {{ t('Custom Link') }}
                                    <input v-model="selectedBlock.config.custom_url" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('https://example.com')">
                                </label>
                                <AppSelect
                                    v-model="selectedBlock.config.access"
                                    :label="t('Access')"
                                    :options="customLinkAccessOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <AppSelect
                                    v-model="selectedBlock.config.display_mode"
                                    :label="t('Display Mode')"
                                    :options="customLinkDisplayModeOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                            </div>
                        </template>

                        <template v-if="selectedBlock.type === 'image'">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Label') }} <span class="text-gray-400">{{ t('(Optional)') }}</span>
                                    <input v-model="selectedBlock.config.title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Optional image label')">
                                </label>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 sm:col-span-2">
                                    {{ t('Link') }}
                                    <input v-model="selectedBlock.config.link" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('https://example.com')">
                                </label>
                                <AppSelect
                                    v-model="selectedBlock.config.target"
                                    :label="t('Link Target')"
                                    :options="customLinkTargetOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        {{ t('Width') }}
                                        <input v-model.number="selectedBlock.config.width" type="number" min="20" max="600" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                                    </label>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        {{ t('Height') }}
                                        <input v-model.number="selectedBlock.config.height" type="number" min="20" max="400" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                                    </label>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">{{ t('Image Upload') }}</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="mt-2 block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                        @change="handleFooterImageUpload"
                                    >
                                    <div v-if="selectedBlock.config.image_url" class="mt-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-800">
                                        <img :src="String(selectedBlock.config.image_url)" :alt="t('Footer image preview')" class="max-h-32 w-full object-contain">
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-if="selectedBlock.type === 'contact_info'">
                            <label v-for="field in ['address', 'phone', 'email']" :key="field" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t(field) }}
                                <input v-model="selectedBlock.config[field]" :type="field === 'email' ? 'email' : 'text'" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="field === 'address' ? t('123 Main Street, City') : field === 'phone' ? t('+1 (555) 123-4567') : t('support@example.com')">
                            </label>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Details') }}
                                <textarea v-model="selectedBlock.config.details" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Add extra contact details or support notes.')"></textarea>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'social_icons'">
                            <AppSelect
                                v-model="selectedBlock.config.display_mode"
                                :label="t('Display Mode')"
                                :options="socialDisplayModeOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                            />
                        </template>

                        <template v-if="selectedBlock.type === 'newsletter' || selectedBlock.type === 'trust_badges'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Description') }}
                                <textarea v-model="selectedBlock.config.description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="selectedBlock.type === 'newsletter' ? t('Invite visitors to subscribe for updates.') : t('Add trust-building copy for this badge block.')"></textarea>
                            </label>
                            <label v-if="selectedBlock.type === 'trust_badges'" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Trust Text') }}
                                <input v-model="selectedBlock.config.text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('Secure payments powered by trusted providers.')">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'recent_blog_posts' || selectedBlock.type === 'ai_tool_categories'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Item Count') }}
                                <input v-model.number="selectedBlock.config.count" type="number" min="1" max="12" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('6')">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'custom_html'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('HTML Content') }}
                                <textarea v-model="selectedBlock.config.content" rows="7" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('<div class=&quot;footer-note&quot;>Trusted by 10,000+ teams</div>')"></textarea>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'divider'">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {{ t('Spacing') }}
                                    <div class="mt-2 flex items-center rounded-lg border border-gray-200 bg-gray-50 focus-within:border-primary-400 focus-within:ring-4 focus-within:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:focus-within:border-primary-500/70 dark:focus-within:ring-primary-500/10">
                                        <input v-model.number="selectedBlock.config.spacing" type="number" min="8" max="96" class="w-full rounded-l-lg bg-transparent px-3 py-2 text-sm text-gray-700 outline-none dark:text-gray-200" :placeholder="t('24')">
                                        <span class="border-l border-gray-200 px-3 text-sm text-gray-500 dark:border-surface-700 dark:text-gray-400">px</span>
                                    </div>
                                </label>
                                <AppColorPicker v-model="selectedBlock.config.color" :label="t('Color')" />
                            </div>
                        </template>

                        <template v-if="selectedBlock.type === 'copyright_text'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Copyright Text') }}
                                <input v-model="selectedBlock.config.text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200" :placeholder="t('© {year} All rights reserved.')">
                                <span class="mt-1 block text-[11px] text-gray-400">{{ t('Use {year} for the current year.') }}</span>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'payment_icons'">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">{{ t('Payment Images') }}</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    class="mt-3 block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                    @change="handlePaymentIconUpload"
                                >
                                <span class="mt-2 block text-[11px] text-gray-400">{{ t('Upload payment logos. Drag to reorder is not available here yet.') }}</span>
                                <div v-if="Array.isArray(selectedBlock.config.icons) && selectedBlock.config.icons.length" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <div v-for="(icon, index) in selectedBlock.config.icons" :key="`${icon}-${index}`" class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-800">
                                        <img :src="String(icon)" :alt="t('Payment icon preview')" class="h-10 w-full object-contain">
                                        <button type="button" class="mt-3 w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300" @click="removePaymentIconImage(index)">
                                            {{ t('Remove') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-if="selectedBlock.type === 'back_to_top'">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <IconClassSelect
                                    v-model="selectedBlock.config.icon"
                                    :label="t('Icon')"
                                />
                                <AppSelect
                                    v-model="selectedBlock.config.shape"
                                    :label="t('Shape')"
                                    :options="backToTopShapeOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <AppColorPicker v-model="selectedBlock.config.bg_color" :label="t('Background Color')" />
                                <AppColorPicker v-model="selectedBlock.config.text_color" :label="t('Text Color')" />
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-950">
                        <div class="flex items-center gap-2">
                            <Tooltip :content="t('Move Up')">
                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" :aria-label="t('Move Up')" @click="moveSelectedBlock(-1)">
                                    <i class="ti ti-arrow-up text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Move Down')">
                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" :aria-label="t('Move Down')" @click="moveSelectedBlock(1)">
                                    <i class="ti ti-arrow-down text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Duplicate')">
                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" :aria-label="t('Duplicate')" @click="duplicateSelectedBlock">
                                    <i class="ti ti-copy text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Remove')">
                                <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-700 hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300" :aria-label="t('Remove')" @click="showRemoveBlockConfirm = true">
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </Tooltip>
                        </div>
                        <button type="button" class="rounded-lg btn-primary px-4 py-2 text-sm font-semibold" @click="closeSettings">{{ t('Done') }}</button>
                    </div>
                </section>
            </div>
        </Teleport>

        <ActionConfirmModal
            :open="showRemoveBlockConfirm && !!selectedBlock"
            :title="t('Remove block?')"
            :message="t('This footer block will be removed from the current layout.')"
            :confirm-label="t('Remove')"
            :cancel-label="t('Cancel')"
            variant="danger"
            @confirm="removeBlock"
            @cancel="showRemoveBlockConfirm = false"
        />

        <!-- Add Element Modal -->
        <Teleport to="body">
            <div v-if="showAddElementModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showAddElementModal = false">
                <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Add Element — :target', { target: addElementTargetLabel }) }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Choose a block to add.') }}</p>
                        </div>
                        <Tooltip :content="t('Close')">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showAddElementModal = false">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </Tooltip>
                    </div>
                    <div class="grid max-h-[70vh] grid-cols-1 gap-3 overflow-y-auto p-6 sm:grid-cols-2">
                        <button
                            v-for="block in availableBlocks"
                            :key="block.type"
                            type="button"
                            class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-800 dark:hover:bg-primary-900/20 rtl:text-right"
                            @click="addElement(block)"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                <i class="ti ti-plus text-base"></i>
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ t(block.label) }}</span>
                                <span class="mt-1 block text-xs text-gray-500">{{ t(block.description) }}</span>
                                <span v-if="block.bottomOnly" class="mt-2 inline-flex rounded-full bg-secondary-100 px-2 py-0.5 text-[10px] font-bold uppercase text-secondary-700 dark:bg-secondary-900/30 dark:text-secondary-300">{{ t('Bottom') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Section Settings Modal -->
        <Teleport to="body">
            <div v-if="showSectionSettings" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showSectionSettings = false">
                <div class="w-full max-w-xl max-h-[80vh] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Footer Settings') }}</h3>
                        </div>
                        <div class="flex items-center gap-3">
                            <Tooltip :content="t('Reset')">
                                <button type="button" class="rounded-lg text-gray-500 hover:text-danger-600 dark:hover:border-danger-800" @click="resetFooter">
                                    <i class="ti ti-restore text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Close')">
                                <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showSectionSettings = false">
                                    <i class="ti ti-x text-lg"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Footer Columns') }}
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                <button v-for="n in 4" :key="n" type="button" class="rounded-lg border px-3 py-2 text-xs font-semibold transition" :class="form.layout === n ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'" @click="setLayout(n)">{{ n }}</button>
                            </div>
                        </label>

                        <AppSelect
                            v-model="form.container_width"
                            :label="t('Container Width')"
                            :options="containerWidthOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                        />

                        <div>
                            <AppSelect
                                v-model="form.column_flex"
                                :label="t('Column Flex')"
                                :options="columnFlexOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                            />
                            <span class="mt-1 block text-xs text-gray-500">{{ t('Which column stretches to fill available space in the main footer grid.') }}</span>
                        </div>

                        <AppColorPicker v-model="form.text_color" :label="t('Text Color')" />

                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60 md:col-span-2">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t('Heading Style') }}</h4>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <AppSelect
                                    v-model="form.heading_style"
                                    :label="t('Style')"
                                    :options="headingStyleOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <AppColorPicker v-model="form.heading_color" :label="t('Color')" />
                                <AppSelect
                                    v-model="form.heading_font_weight"
                                    :label="t('Font Weight')"
                                    :options="headingWeightOptions.map((option) => ({ label: `${t(option.label)} (${option.value})`, value: option.value }))"
                                />
                                <AppSelect
                                    v-model="form.heading_text_transform"
                                    :label="t('Text Transform')"
                                    :options="headingTransformOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                                />
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Font Size') }}
                                    <div class="mt-2 flex items-center gap-2">
                                        <input
                                            :value="parseInt(form.heading_font_size, 10) || 12"
                                            type="number"
                                            min="8"
                                            max="64"
                                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                            @input="form.heading_font_size = `${($event.target as HTMLInputElement).value}px`"
                                        >
                                        <span class="shrink-0 text-xs text-gray-500">px</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60 md:col-span-2">
                            <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">{{ t('Background') }}</h4>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <AppColorPicker v-model="form.background.color" :label="t('Background Color')" />
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ t('Overlay Opacity') }}
                                    <input v-model.number="form.background.overlay_opacity" type="number" min="0" max="1" step="0.1" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white">
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
                                            @change="handleBackgroundUpload"
                                        >
                                        <div v-if="form.background.image_url" class="space-y-3">
                                            <img :src="String(form.background.image_url)" :alt="t('Footer background preview')" class="h-32 w-full rounded-xl border border-gray-200 object-cover dark:border-surface-700">
                                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300" @click="clearBackgroundImage">
                                                <i class="ti ti-trash text-base"></i>
                                                {{ t('Remove Image') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('Custom CSS') }}
                            <textarea v-model="form.custom_css" rows="3" :maxlength="2000" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('.footer-grid { border-top: 2px solid var(--color-primary-500); }')"></textarea>
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800">
                        <button type="button" class="rounded-lg btn-primary" @click="showSectionSettings = false">{{ t('Done') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Bottom Settings Modal -->
        <Teleport to="body">
            <div v-if="showBottomSettings" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showBottomSettings = false">
                <div class="w-full max-w-xl max-h-[80vh] overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Bottom Bar Settings') }}</h3>
                        </div>
                        <Tooltip :content="t('Close')">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showBottomSettings = false">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </Tooltip>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Padding') }}
                            <div class="mt-2 flex items-center rounded-lg border border-gray-200 bg-gray-50 focus-within:border-primary-400 focus-within:ring-4 focus-within:ring-primary-100 dark:border-surface-700 dark:bg-surface-800 dark:focus-within:border-primary-500/70 dark:focus-within:ring-primary-500/10">
                                <input v-model.number="form.bottom_bar.padding" type="number" min="8" max="80" class="w-full rounded-l-lg bg-transparent px-3 py-2 text-sm outline-none dark:text-white">
                                <span class="border-l border-gray-200 px-3 text-sm text-gray-500 dark:border-surface-700 dark:text-gray-400">px</span>
                            </div>
                        </label>

                        <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                            <div>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Top Border') }}</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Show a divider above the bottom bar.') }}</span>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.bottom_bar.border_top"
                                class="relative inline-flex h-6 w-11 rounded-full transition"
                                :class="form.bottom_bar.border_top ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                @click="form.bottom_bar.border_top = !form.bottom_bar.border_top"
                            >
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.bottom_bar.border_top ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>
                        </div>

                        <AppColorPicker v-model="form.bottom_bar.bg_color" :label="t('Background Color')" />
                        <AppColorPicker v-model="form.bottom_bar.text_color" :label="t('Text Color')" />

                        <div class="md:col-span-2">
                            <AppSelect
                                v-model="form.bottom_bar.column_flex"
                                :label="t('Column Flex')"
                                :options="bottomColumnFlexOptions.map((option) => ({ label: t(option.label), value: option.value }))"
                            />
                            <span class="mt-1 block text-xs text-gray-500">{{ t('Desktop: left column left-aligned, right column right-aligned. Mobile: centered, flex-column.') }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800">
                        <button type="button" class="rounded-lg btn-primary" @click="showBottomSettings = false">{{ t('Done') }}</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Import Modal -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showImportModal = false">
                <div class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Import Footer Config') }}</h3>
                        <Tooltip :content="t('Close')">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showImportModal = false">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </Tooltip>
                    </div>
                    <div class="p-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Paste JSON config') }}
                            <textarea v-model="importJsonText" rows="10" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" placeholder='{ "layout": 4, "columns": [...], ... }'></textarea>
                        </label>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800" @click="showImportModal = false">{{ t('Cancel') }}</button>
                            <button type="button" class="rounded-lg btn-primary px-4 py-2 text-sm font-semibold" @click="importConfig">{{ t('Import') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style scoped>
.footer-block-ghost,
.footer-column-ghost {
    opacity: 0.45;
}

.footer-block-chosen {
    box-shadow: 0 10px 24px rgb(16 185 129 / 0.14);
}
</style>
