<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

declare const route: (name: string, params?: unknown) => string

type FooterBlockType = 'about_text' | 'menu_list' | 'contact_info' | 'social_icons' | 'newsletter' | 'custom_html' | 'recent_blog_posts' | 'ai_tool_categories' | 'legal_links' | 'language_switcher' | 'dark_mode' | 'trust_badges' | 'store_badges' | 'divider' | 'copyright_text' | 'payment_icons' | 'back_to_top'
type ConfigValue = string | number | null | string[]
type PreviewMode = 'desktop' | 'tablet' | 'mobile'
type HeadingStyle = 'default' | 'accent' | 'minimal'
type BottomAlignment = 'left' | 'center' | 'right' | 'between'

interface FooterBlock {
    id: string
    type: FooterBlockType
    enabled: boolean
    config: Record<string, ConfigValue>
}

interface FooterColumn {
    id: string
    width: number
    title: string
    subtitle: string
    heading_style: HeadingStyle
    blocks: FooterBlock[]
}

interface FooterBottomBar {
    copyright_text: string
    menu_slug: string | null
    show_payment_icons: boolean
    payment_icons: string[]
    show_back_to_top: boolean
    layout_desktop: number
    layout_tablet: number
    layout_mobile: number
    alignment_desktop: BottomAlignment
    alignment_tablet: BottomAlignment
    alignment_mobile: BottomAlignment
    padding_desktop: number
    padding_tablet: number
    padding_mobile: number
    border_top: boolean
}

interface FooterBottomColumn {
    id: 'left' | 'right'
    title: string
    blocks: FooterBlock[]
}

interface FooterConfig {
    layout: number
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
    aiCategories: AiCategoryOption[]
}>()

const { t } = useTranslate()

let blockIdSequence = 0
const selectedColumnIndex = ref<number | null>(null)
const selectedBottomColumnIndex = ref<number | null>(null)
const selectedBlockIndex = ref<number | null>(null)
const selectedZone = ref<'columns' | 'bottom'>('columns')
const previewMode = ref<PreviewMode>('desktop')
const bottomSettingsDevice = ref<PreviewMode>('desktop')
const isDragging = ref(false)

const createId = (type: string) => {
    blockIdSequence += 1

    return `${type}_${Date.now().toString(36)}_${blockIdSequence.toString(36)}_${Math.random().toString(36).slice(2, 7)}`
}

const defaultWidth = (layout: number) => {
    if (layout === 1) return 100
    if (layout === 2) return 50
    if (layout === 3) return 33

    return 25
}

const defaultBottomBar = (bottomBar?: Partial<FooterBottomBar>): FooterBottomBar => ({
    copyright_text: bottomBar?.copyright_text ?? '',
    menu_slug: bottomBar?.menu_slug ?? null,
    show_payment_icons: bottomBar?.show_payment_icons ?? true,
    payment_icons: bottomBar?.payment_icons ?? [],
    show_back_to_top: bottomBar?.show_back_to_top ?? true,
    layout_desktop: 2,
    layout_tablet: 2,
    layout_mobile: 2,
    alignment_desktop: bottomBar?.alignment_desktop ?? 'between',
    alignment_tablet: bottomBar?.alignment_tablet ?? 'center',
    alignment_mobile: bottomBar?.alignment_mobile ?? 'center',
    padding_desktop: Number(bottomBar?.padding_desktop ?? 32),
    padding_tablet: Number(bottomBar?.padding_tablet ?? 24),
    padding_mobile: Number(bottomBar?.padding_mobile ?? 20),
    border_top: bottomBar?.border_top ?? true,
})

const normalizeConfig = (config: FooterConfig): FooterConfig => {
    const cloned = JSON.parse(JSON.stringify(config)) as FooterConfig
    cloned.layout = Math.max(1, Math.min(4, Number(cloned.layout || 4)))
    cloned.bottom_bar = defaultBottomBar(cloned.bottom_bar)
    cloned.bottom_blocks = cloned.bottom_blocks ?? []
    const legacyBottomBlocks = cloned.bottom_blocks
    const bottomColumns = cloned.bottom_columns ?? []
    const normalizedBottomColumns: FooterBottomColumn[] = [
        {
            id: 'left',
            title: t('Left Column'),
            blocks: bottomColumns[0]?.blocks ?? legacyBottomBlocks,
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
        width: Number(column.width || defaultWidth(cloned.layout)),
        title: column.title ?? '',
        subtitle: column.subtitle ?? '',
        heading_style: column.heading_style ?? 'default',
        blocks: (column.blocks ?? []).map((block) => ({
            ...block,
            enabled: block.enabled ?? true,
            config: block.config ?? {},
        })),
    }))

    while (cloned.columns.length < cloned.layout) {
        cloned.columns.push({
            id: `footer_column_${cloned.columns.length + 1}`,
            width: defaultWidth(cloned.layout),
            title: '',
            subtitle: '',
            heading_style: 'default',
            blocks: [],
        })
    }

    return cloned
}

const form = useForm<FooterConfig>(normalizeConfig(props.config))

const availableBlocks: BlockPaletteItem[] = [
    { type: 'about_text', label: 'About Text', description: 'Logo, alt text, and short brand description.', config: { logo: null, alt: '', description: '' } },
    { type: 'menu_list', label: 'Menu List', description: 'Render links from a saved menu.', config: { title: t('Quick Links'), menu_slug: '' } },
    { type: 'contact_info', label: 'Contact Info', description: 'Address, phone, and email rows with icons.', config: { title: t('Contact Us'), address: '', phone: '', email: '' } },
    { type: 'social_icons', label: 'Social Icons', description: 'Show configured social follow buttons.', config: { title: t('Follow Us'), display_mode: 'icons' } },
    { type: 'newsletter', label: 'Newsletter Form', description: 'Embed the public newsletter subscription form.', config: { title: t('Subscribe'), description: t('Get the latest updates.') } },
    { type: 'custom_html', label: 'Custom HTML', description: 'Sanitized trusted footer markup.', config: { title: '', content: '' } },
    { type: 'recent_blog_posts', label: 'Recent Blog Posts', description: 'Show latest published posts.', config: { title: t('Latest Posts'), count: 3 } },
    { type: 'ai_tool_categories', label: 'AI Tool Categories', description: 'List active AI tool categories.', config: { title: t('AI Tools'), count: 6 } },
    { type: 'legal_links', label: 'Legal Links', description: 'Privacy, terms, refund, and contact links.', config: { title: t('Legal'), links: ['privacy', 'terms', 'refund', 'contact'] } },
    { type: 'language_switcher', label: 'Language Switcher', description: 'Locale selector slot.', config: { title: '' } },
    { type: 'dark_mode', label: 'Dark Mode Toggle', description: 'Theme toggle slot.', config: { title: '' } },
    { type: 'trust_badges', label: 'Trust Badges', description: 'Payment security or guarantee text.', config: { title: t('Secure Checkout'), text: t('Secure payments powered by trusted providers.') } },
    { type: 'store_badges', label: 'Store Badges', description: 'External CTA buttons or app store badges.', config: { title: t('Get the App'), links: [] } },
    { type: 'divider', label: 'Divider / Spacer', description: 'Visual divider or spacing block.', config: { spacing: 24 } },
    { type: 'copyright_text', label: 'Copyright Text', description: 'Copyright text with {year} support.', bottomOnly: true, config: { text: form.bottom_bar.copyright_text || t('© {year} All rights reserved.') } },
    { type: 'payment_icons', label: 'Payment Icons', description: 'Accepted payment method badges.', bottomOnly: true, config: { icons: form.bottom_bar.payment_icons.length ? [...form.bottom_bar.payment_icons] : ['visa', 'mastercard', 'paypal', 'stripe'] } },
    { type: 'back_to_top', label: 'Back to Top', description: 'Scroll-to-top control.', bottomOnly: true, config: { label: t('Back to top') } },
]

const columnWidths = [25, 33, 50, 66, 75, 100]
const paymentIcons = ['visa', 'mastercard', 'paypal', 'stripe', 'amex', 'discover', 'apple_pay', 'google_pay']
const headingStyles: Array<{ value: HeadingStyle; label: string }> = [
    { value: 'default', label: 'Default' },
    { value: 'accent', label: 'Accent' },
    { value: 'minimal', label: 'Minimal' },
]
const bottomAlignments: Array<{ value: BottomAlignment; label: string }> = [
    { value: 'left', label: 'Left' },
    { value: 'center', label: 'Center' },
    { value: 'right', label: 'Right' },
    { value: 'between', label: 'Space Between' },
]

const paletteDragOptions = {
    group: { name: 'footer-blocks', pull: 'clone' as const, put: false },
    sort: false,
    clone: (item: BlockPaletteItem): FooterBlock => ({
        id: createId(item.type),
        type: item.type,
        enabled: true,
        config: JSON.parse(JSON.stringify(item.config)) as Record<string, ConfigValue>,
    }),
}

const blockDragOptions = {
    group: { name: 'footer-blocks', pull: true as const, put: true },
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

const previewGridClass = computed(() => {
    if (previewMode.value === 'mobile') return 'grid-cols-1'
    if (previewMode.value === 'tablet') return form.layout > 1 ? 'grid-cols-2' : 'grid-cols-1'
    if (form.layout === 1) return 'grid-cols-1'
    if (form.layout === 2) return 'grid-cols-2'
    if (form.layout === 3) return 'grid-cols-3'

    return 'grid-cols-4'
})

const bottomAlignmentField = computed(() => `alignment_${bottomSettingsDevice.value}` as keyof FooterBottomBar)
const bottomPaddingField = computed(() => `padding_${bottomSettingsDevice.value}` as keyof FooterBottomBar)

const blockLabel = (type: FooterBlockType) => availableBlocks.find((block) => block.type === type)?.label ?? type
const blockDescription = (type: FooterBlockType) => availableBlocks.find((block) => block.type === type)?.description ?? ''

const setLayout = (layout: number) => {
    form.layout = layout
    const width = defaultWidth(layout)

    while (form.columns.length < layout) {
        form.columns.push({
            id: `footer_column_${form.columns.length + 1}`,
            width,
            title: '',
            subtitle: '',
            heading_style: 'default',
            blocks: [],
        })
    }

    form.columns = form.columns.slice(0, layout).map((column) => ({
        ...column,
        width: column.width || width,
        title: column.title ?? '',
        subtitle: column.subtitle ?? '',
        heading_style: column.heading_style ?? 'default',
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

const togglePaymentIcon = (icons: ConfigValue, icon: string) => {
    if (!Array.isArray(icons)) return

    const index = icons.indexOf(icon)
    if (index >= 0) {
        icons.splice(index, 1)
        return
    }

    icons.push(icon)
}

const submit = () => {
    form.bottom_bar.layout_desktop = 2
    form.bottom_bar.layout_tablet = 2
    form.bottom_bar.layout_mobile = 2
    form.bottom_blocks = form.bottom_columns.flatMap((column) => column.blocks)

    form.post(route('admin.footer.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Footer Builder')" />

    <AdminLayout>
        <template #title>{{ t('Appearance') }}</template>

        <div class="space-y-6">
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Footer Builder') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Drag footer widgets into columns and bottom bar zones.') }}</p>
                </div>
                <button type="button" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60" @click="submit">
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z" /></svg>
                    <i v-else class="ti ti-device-floppy text-base"></i>
                    {{ form.processing ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="xl:sticky xl:top-6 xl:self-start">
                    <section class="flex max-h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
                        <div class="border-b border-gray-100 p-5 dark:border-surface-800">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Block Palette') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t('Drag a widget into any footer drop zone.') }}</p>
                        </div>

                        <VueDraggable
                            :model-value="availableBlocks"
                            class="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-y-auto p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-1"
                            v-bind="paletteDragOptions"
                            @start="isDragging = true"
                            @end="isDragging = false"
                        >
                            <div
                                v-for="block in availableBlocks"
                                :key="block.type"
                                :data-type="block.type"
                                class="cursor-grab rounded-xl border border-gray-200 bg-gray-50 p-3 transition hover:border-primary-200 hover:bg-primary-50 active:cursor-grabbing dark:border-surface-700 dark:bg-surface-800 dark:hover:bg-primary-900/20"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                        <i class="ti ti-plus text-base"></i>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-bold text-gray-900 dark:text-white">{{ t(block.label) }}</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-gray-500">{{ t(block.description) }}</span>
                                        <span v-if="block.bottomOnly" class="mt-2 inline-flex rounded-full bg-secondary-100 px-2 py-0.5 text-[10px] font-bold uppercase text-secondary-700">{{ t('Bottom') }}</span>
                                    </span>
                                </div>
                            </div>
                        </VueDraggable>
                    </section>
                </aside>

                <main class="min-w-0 space-y-6">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-4 flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Main Footer Grid') }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ t('Drag columns left or right, then drop widgets inside each column.') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="grid grid-cols-4 gap-2">
                                    <button v-for="n in 4" :key="n" type="button" class="rounded-lg border px-3 py-2 text-xs font-semibold transition" :class="form.layout === n ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'" @click="setLayout(n)">
                                        {{ n }}
                                    </button>
                                </div>
                                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-800">
                                    <button v-for="mode in ['desktop', 'tablet', 'mobile'] as PreviewMode[]" :key="mode" type="button" class="rounded-md px-3 py-1.5 text-xs font-semibold capitalize transition" :class="previewMode === mode ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400'" @click="previewMode = mode">
                                        {{ t(mode) }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <VueDraggable
                            v-model="form.columns"
                            class="grid gap-4"
                            :class="previewGridClass"
                            v-bind="columnDragOptions"
                        >
                            <section v-for="(column, columnIndex) in form.columns" :key="column.id" class="min-h-80 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-950">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <button type="button" class="footer-column-drag-handle inline-flex cursor-grab items-center gap-2 rounded-lg px-2 py-1 text-xs font-bold uppercase tracking-wide text-gray-500 hover:bg-white hover:text-primary-700 active:cursor-grabbing dark:hover:bg-surface-900">
                                        <i class="ti ti-grip-vertical text-base"></i>
                                        {{ t('Column :count', { count: columnIndex + 1 }) }}
                                    </button>
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-white hover:text-primary-600 disabled:opacity-30 dark:hover:bg-surface-900" :disabled="columnIndex === 0" :aria-label="t('Move column left')" @click="moveColumn(Number(columnIndex), -1)">
                                            <i class="ti ti-chevron-left text-base"></i>
                                        </button>
                                        <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-white hover:text-primary-600 disabled:opacity-30 dark:hover:bg-surface-900" :disabled="columnIndex === form.columns.length - 1" :aria-label="t('Move column right')" @click="moveColumn(Number(columnIndex), 1)">
                                            <i class="ti ti-chevron-right text-base"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3 grid gap-2">
                                    <input v-model="column.title" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200" :placeholder="t('Column heading')">
                                    <input v-model="column.subtitle" type="text" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200" :placeholder="t('Column sub heading')">
                                    <div class="grid grid-cols-2 gap-2">
                                        <select v-model="column.heading_style" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                            <option v-for="style in headingStyles" :key="style.value" :value="style.value">{{ t(style.label) }}</option>
                                        </select>
                                        <select v-model.number="column.width" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200">
                                            <option v-for="width in columnWidths" :key="width" :value="width">{{ width }}%</option>
                                        </select>
                                    </div>
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
                                                <button type="button" class="footer-block-drag-handle cursor-grab rounded-lg p-2 text-gray-400 hover:bg-white hover:text-primary-600 active:cursor-grabbing dark:hover:bg-surface-900" :aria-label="t('Drag block')">
                                                    <i class="ti ti-grip-vertical text-base"></i>
                                                </button>
                                                <div class="min-w-0">
                                                    <div class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ t(blockLabel(block.type)) }}</div>
                                                    <div class="truncate text-[11px] text-gray-400">{{ t(blockDescription(block.type)) }}</div>
                                                </div>
                                            </div>
                                            <button type="button" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300" :aria-label="t('Settings')" @click="openSettings('columns', Number(blockIndex), Number(columnIndex))">
                                                <i class="ti ti-settings text-base"></i>
                                            </button>
                                        </div>
                                    </article>
                                </VueDraggable>

                                <p v-if="column.blocks.length === 0" class="mt-3 text-center text-xs text-gray-400">{{ t('Drop footer widgets here.') }}</p>
                            </section>
                        </VueDraggable>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                        <div class="mb-5 flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-surface-800 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t('Bottom Bar') }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ t('Drag copyright, payment, menu, social, and back-to-top blocks into the bottom bar.') }}</p>
                            </div>
                            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-800">
                                <button v-for="device in ['desktop', 'tablet', 'mobile'] as PreviewMode[]" :key="device" type="button" class="rounded-md px-3 py-1.5 text-xs font-semibold capitalize transition" :class="bottomSettingsDevice === device ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400'" @click="bottomSettingsDevice = device">
                                    {{ t(device) }}
                                </button>
                            </div>
                        </div>

                        <div class="mb-5 grid gap-4 md:grid-cols-3">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Alignment') }}
                                <select v-model="form.bottom_bar[bottomAlignmentField]" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                                    <option v-for="alignment in bottomAlignments" :key="alignment.value" :value="alignment.value">{{ t(alignment.label) }}</option>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Padding') }}
                                <input v-model.number="form.bottom_bar[bottomPaddingField]" type="number" min="8" max="80" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Top Border') }}</span>
                                <input v-model="form.bottom_bar.border_top" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <section v-for="(bottomColumn, bottomColumnIndex) in form.bottom_columns" :key="bottomColumn.id" class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-3 dark:border-surface-700 dark:bg-surface-950">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ t(bottomColumn.title) }}</h4>
                                    <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-gray-400 dark:bg-surface-900">{{ bottomColumn.blocks.length }}</span>
                                </div>
                                <VueDraggable v-model="bottomColumn.blocks" class="flex min-h-24 flex-col gap-3" v-bind="blockDragOptions">
                                    <article v-for="(block, blockIndex) in bottomColumn.blocks" :key="block.id" :data-type="block.type" class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 dark:border-primary-900/50 dark:bg-primary-900/10">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <button type="button" class="footer-block-drag-handle cursor-grab text-gray-400 hover:text-primary-600" :aria-label="t('Drag block')">
                                                <i class="ti ti-grip-vertical text-base"></i>
                                            </button>
                                            <span class="truncate text-xs font-bold text-gray-800 dark:text-gray-100">{{ t(blockLabel(block.type)) }}</span>
                                        </div>
                                        <button type="button" class="rounded-lg p-2 text-primary-700 hover:bg-white dark:text-primary-300 dark:hover:bg-surface-900" :aria-label="t('Settings')" @click="openSettings('bottom', Number(blockIndex), null, Number(bottomColumnIndex))">
                                            <i class="ti ti-settings text-base"></i>
                                        </button>
                                    </article>
                                </VueDraggable>
                            </section>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="selectedBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="closeSettings">
                <section class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-900 dark:text-white">{{ t(blockLabel(selectedBlock.type)) }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ t(blockDescription(selectedBlock.type)) }}</p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="closeSettings">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-5 overflow-y-auto px-6 py-5">
                        <label class="flex items-center justify-between gap-4">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ t('Enabled') }}</span>
                            <input v-model="selectedBlock.enabled" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        </label>

                        <label v-if="selectedBlock.config.title !== undefined" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                            {{ t('Block Title') }}
                            <input v-model="selectedBlock.config.title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                        </label>

                        <template v-if="selectedBlock.type === 'about_text'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Logo URL') }}
                                <input v-model="selectedBlock.config.logo" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Alt Text') }}
                                <input v-model="selectedBlock.config.alt" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Description') }}
                                <textarea v-model="selectedBlock.config.description" rows="4" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200"></textarea>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'menu_list'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Select Menu') }}
                                <select v-model="selectedBlock.config.menu_slug" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                                    <option value="">{{ t('Select a menu') }}</option>
                                    <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                                </select>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'contact_info'">
                            <label v-for="field in ['address', 'phone', 'email']" :key="field" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t(field) }}
                                <input v-model="selectedBlock.config[field]" :type="field === 'email' ? 'email' : 'text'" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'newsletter' || selectedBlock.type === 'trust_badges'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Description') }}
                                <textarea v-model="selectedBlock.config.description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200"></textarea>
                            </label>
                            <label v-if="selectedBlock.type === 'trust_badges'" class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Trust Text') }}
                                <input v-model="selectedBlock.config.text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'recent_blog_posts' || selectedBlock.type === 'ai_tool_categories'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Item Count') }}
                                <input v-model.number="selectedBlock.config.count" type="number" min="1" max="12" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'custom_html'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('HTML Content') }}
                                <textarea v-model="selectedBlock.config.content" rows="7" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200"></textarea>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'divider'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Spacing') }}
                                <input v-model.number="selectedBlock.config.spacing" type="number" min="8" max="96" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'copyright_text'">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                                {{ t('Copyright Text') }}
                                <input v-model="selectedBlock.config.text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200">
                                <span class="mt-1 block text-[11px] text-gray-400">{{ t('Use {year} for the current year.') }}</span>
                            </label>
                        </template>

                        <template v-if="selectedBlock.type === 'payment_icons'">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">{{ t('Payment Icons') }}</label>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="icon in paymentIcons" :key="icon" type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold capitalize transition" :class="Array.isArray(selectedBlock.config.icons) && selectedBlock.config.icons.includes(icon) ? 'border-primary-300 bg-primary-100 text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300' : 'border-gray-200 bg-gray-50 text-gray-500 hover:border-primary-200 dark:border-surface-700 dark:bg-surface-800'" @click="togglePaymentIcon(selectedBlock.config.icons, icon)">
                                        {{ icon.replace('_', ' ') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-950 sm:grid-cols-4">
                        <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="moveSelectedBlock(-1)">{{ t('Move Up') }}</button>
                        <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="moveSelectedBlock(1)">{{ t('Move Down') }}</button>
                        <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="duplicateSelectedBlock">{{ t('Duplicate') }}</button>
                        <button type="button" class="rounded-lg bg-danger-50 px-3 py-2 text-xs font-semibold text-danger-700 hover:bg-danger-100 dark:bg-danger-900/20 dark:text-danger-300" @click="removeBlock">{{ t('Remove') }}</button>
                    </div>
                </section>
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
