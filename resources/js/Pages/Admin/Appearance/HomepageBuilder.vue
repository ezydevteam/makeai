<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html'

declare const route: (name: string, params?: Record<string, string | number>) => string

type SectionItem = Record<string, string | number | boolean>
type SectionConfigValue = string | number | boolean | string[] | SectionItem[]

type SectionConfig = Record<string, SectionConfigValue>

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
    preloader: {
        enabled: boolean
        animation_url: string
    }
    scroll_to_top: {
        enabled: boolean
        position: 'left' | 'right'
        show_after_px: number
    }
    cookie_consent: {
        enabled: boolean
        message: string
        accept_text: string
        policy_url: string
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
}>()

const { t } = useTranslate()
const isCustomHomepage = computed(() => props.activeHomepageTemplate === 'default')

const homepageTemplateForm = useForm({
    homepage_template: props.activeHomepageTemplate,
})

const form = useForm<HomepageConfig>({
    sections: props.config.sections,
    settings: props.config.settings,
})

const sectionCatalog: SectionMeta[] = [
    { type: 'hero', label: t('Hero Section'), description: t('Headline, CTAs, hero media, trust badges, and counters.') },
    { type: 'features', label: t('Features Section'), description: t('Feature cards with icons, descriptions, and optional CTA.') },
    { type: 'tools_showcase', label: t('AI Tools Showcase'), description: t('Tool grid, carousel, tabs, or masonry showcase.') },
    { type: 'how_it_works', label: t('How It Works'), description: t('Numbered process steps in horizontal or timeline layout.') },
    { type: 'pricing', label: t('Pricing Section'), description: t('Database plans or custom static pricing table.') },
    { type: 'testimonials', label: t('Testimonials'), description: t('Customer quotes from database or manual entries.') },
    { type: 'faq', label: t('FAQ Section'), description: t('Accordion FAQs from database, page, or manual entries.') },
    { type: 'stats_bar', label: t('Stats / Social Proof'), description: t('Counters and partner logo cloud.') },
    { type: 'cta_banner', label: t('CTA Banner'), description: t('Conversion banner with background and buttons.') },
    { type: 'latest_posts', label: t('Blog / Latest Posts'), description: t('Recent posts grid, list, or featured-first layout.') },
    { type: 'newsletter', label: t('Newsletter Section'), description: t('Newsletter subscription block.') },
    { type: 'integrations', label: t('Technology Logos'), description: t('AI model or integration logo ticker/grid.') },
    { type: 'custom_html', label: t('Custom HTML'), description: t('Embed custom HTML, CSS, or scripts.') },
]

const addSectionModalOpen = ref(false)
const sectionModalOpen = ref(false)
const settingsModalOpen = ref(false)
const mobilePreview = ref(false)
const editingSection = ref<EditingSection | null>(null)
const removeTargetIndex = ref<number | null>(null)
const draggedIndex = ref<number | null>(null)
const dragOverIndex = ref<number | null>(null)
const dragPosition = ref({ x: 0, y: 0 })

const availableSections = computed(() => sectionCatalog.filter((section) => props.sectionTypes.includes(section.type)))

const enabledSectionsCount = computed(() => form.sections.filter((section) => section.enabled).length)
const draggedSection = computed(() => draggedIndex.value === null ? null : form.sections[draggedIndex.value] ?? null)

const getSectionMeta = (type: SectionType): SectionMeta => sectionCatalog.find((section) => section.type === type) ?? sectionCatalog[0]
const configLabel = (key: string | number): string => t(String(key).replaceAll('_', ' '))

const cloneSection = (section: HomepageSection): HomepageSection => JSON.parse(JSON.stringify(section)) as HomepageSection

const submit = () => {
    form.post(route('admin.homepage.update'), {
        preserveScroll: true,
    })
}

const openPreview = () => {
    window.open(route('home'), '_blank', 'noopener,noreferrer')
}

const moveDraggedSection = (targetIndex: number) => {
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) {
        draggedIndex.value = null
        dragOverIndex.value = null
        return
    }

    const sourceIndex = draggedIndex.value
    const movedSection = form.sections.splice(sourceIndex, 1)[0]
    const adjustedTargetIndex = targetIndex > sourceIndex ? targetIndex - 1 : targetIndex
    form.sections.splice(adjustedTargetIndex, 0, movedSection)
    draggedIndex.value = null
    dragOverIndex.value = null
}

const startPointerDrag = (event: PointerEvent, index: number) => {
    const target = event.target as HTMLElement

    if (target.closest('button')) return

    draggedIndex.value = index
    dragOverIndex.value = index
    dragPosition.value = { x: event.clientX, y: event.clientY }
    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', onPointerUp, { once: true })
}

const onPointerMove = (event: PointerEvent) => {
    if (draggedIndex.value === null) return
    dragPosition.value = { x: event.clientX, y: event.clientY }
}

const onPointerUp = () => {
    moveDraggedSection(dragOverIndex.value ?? draggedIndex.value ?? 0)
    window.removeEventListener('pointermove', onPointerMove)
}

const onDragOver = (index: number) => {
    if (draggedIndex.value === null) return
    dragOverIndex.value = index
}

const onCardDragOver = (index: number) => {
    if (draggedIndex.value === null) return
    dragOverIndex.value = index > draggedIndex.value ? index + 1 : index
}

const moveSectionUp = (index: number) => {
    if (index === 0) return
    const previous = form.sections[index - 1]
    form.sections[index - 1] = form.sections[index]
    form.sections[index] = previous
}

const moveSectionDown = (index: number) => {
    if (index >= form.sections.length - 1) return
    const next = form.sections[index + 1]
    form.sections[index + 1] = form.sections[index]
    form.sections[index] = next
}

const toggleSection = (index: number) => {
    form.sections[index].enabled = !form.sections[index].enabled
}

const editSection = (index: number) => {
    editingSection.value = {
        index,
        data: cloneSection(form.sections[index]),
    }
    sectionModalOpen.value = true
}

const saveSectionSettings = () => {
    if (editingSection.value === null) return
    form.sections[editingSection.value.index] = cloneSection(editingSection.value.data)
    sectionModalOpen.value = false
}

const removeSection = (index: number) => {
    const section = form.sections[index]
    if (section.core) return
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
            layout: 'centered',
            headline: t('Create more with {app_name}'),
            subheadline: t('Launch your AI-powered workflow from one polished platform.'),
            primary_cta_text: t('Get Started'),
            primary_cta_link: '/register',
            primary_cta_style: 'filled',
            secondary_cta_text: t('View Pricing'),
            secondary_cta_link: '/pricing',
            secondary_cta_style: 'outline',
            background_type: 'gradient',
            background_value: '',
            hero_media_url: '',
            typing_phrases: [t('Create content'), t('Generate images'), t('Write code')],
            show_trust_badges: true,
            trust_badge_text: t('Trusted by creators worldwide'),
            stats: [],
        }
    }

    if (type === 'features') {
        return {
            title,
            subtitle: t('Highlight your platform advantages.'),
            layout: '3-column',
            items: [],
            cta_text: '',
            cta_link: '',
        }
    }

    if (type === 'custom_html') {
        return {
            title,
            content: '',
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
        secondary_text: '',
        secondary_link: '',
        background: 'default',
    }
}

const addListItem = (key: string) => {
    if (editingSection.value === null) return
    const value = editingSection.value.data.config[key]
    const item: SectionItem = key === 'stats'
        ? { number: '100K+', label: t('Generated results') }
        : { icon: 'sparkles', title: t('New item'), description: t('Describe this item.'), image_url: '' }

    editingSection.value.data.config[key] = Array.isArray(value) && value.every((entry) => typeof entry !== 'string') ? [...value, item] : [item]
}

const removeListItem = (key: string, index: number) => {
    if (editingSection.value === null) return
    const value = editingSection.value.data.config[key]
    if (!Array.isArray(value)) return
    editingSection.value.data.config[key] = value.filter((entry, itemIndex) => typeof entry !== 'string' && itemIndex !== index) as SectionItem[]
}

const setConfigString = (key: string, value: string) => {
    if (editingSection.value === null) return
    editingSection.value.data.config[key] = value
}

const setItemString = (item: SectionItem, key: string, value: string) => {
    item[key] = value
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
                    <button @click="mobilePreview = !mobilePreview" type="button" :class="mobilePreview ? 'btn-primary' : 'bg-white dark:bg-surface-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-surface-700'" class="px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm">
                        {{ mobilePreview ? t('Mobile Preview On') : t('Mobile Preview') }}
                    </button>
                    <button @click="settingsModalOpen = true" type="button" class="px-4 py-2.5 bg-white dark:bg-surface-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-surface-800 transition-all shadow-sm">{{ t('General Settings') }}</button>
                    <button @click="openPreview" type="button" class="px-4 py-2.5 bg-white dark:bg-surface-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-surface-800 transition-all shadow-sm">{{ t('Live Preview') }}</button>
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
            <div v-if="isCustomHomepage" class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <div class="xl:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6">
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ t('Homepage Sections') }}</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ t(':enabled enabled of :total sections.', { enabled: enabledSectionsCount, total: form.sections.length }) }}</p>
                            </div>
                            <button @click="addSectionModalOpen = true" type="button" class="px-4 py-2 bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 rounded-lg text-sm font-bold hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors">{{ t('Add Section') }}</button>
                        </div>

                        <div v-if="form.sections.length === 0" class="border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-2xl p-10 text-center bg-gray-50/50 dark:bg-surface-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1">{{ t('No sections yet') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ t('Add a section to start building your homepage.') }}</p>
                            <button @click="addSectionModalOpen = true" type="button" class="px-4 py-2 btn-primary rounded-lg text-sm font-bold">{{ t('Add first section') }}</button>
                        </div>

                        <div class="space-y-2">
                            <template v-for="(section, index) in form.sections" :key="section.id">
                                <div
                                    @pointerenter="onDragOver(index)"
                                    :class="draggedIndex !== null && dragOverIndex === index ? 'h-14 border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 shadow-sm' : draggedIndex !== null ? 'h-8 border-gray-300 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 text-gray-400 dark:text-gray-500' : 'h-3 border-transparent text-transparent'"
                                    class="flex items-center justify-center rounded-xl border-2 border-dashed text-xs font-black uppercase tracking-widest transition-all duration-150"
                                >
                                    {{ t('Drop section here') }}
                                </div>
                                <div @pointerdown="startPointerDrag($event, index)" @pointerenter="onCardDragOver(index)" :class="draggedIndex === index ? 'border-primary-500 bg-white dark:bg-surface-900 ring-2 ring-primary-500 ring-dashed shadow-xl opacity-60' : section.enabled ? 'border-primary-200 bg-primary-50 dark:border-primary-900/50 dark:bg-surface-900' : 'border-gray-200 bg-gray-50 dark:border-surface-700 dark:bg-surface-800'" class="group flex items-center gap-4 rounded-2xl border p-4 transition-all hover:shadow-md cursor-grab active:cursor-grabbing select-none touch-none">
                                <div class="flex flex-col gap-1 text-gray-400">
                                    <button @click.prevent="moveSectionUp(index)" :disabled="index === 0" class="hover:text-primary-500 disabled:opacity-30 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg></button>
                                    <button @click.prevent="moveSectionDown(index)" :disabled="index === form.sections.length - 1" class="hover:text-primary-500 disabled:opacity-30 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg></button>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ getSectionMeta(section.type).label }}</h3>
                                        <span v-if="section.core" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-800 text-gray-500">{{ t('Core') }}</span>
                                        <span :class="section.enabled ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-surface-800'" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md">{{ section.enabled ? t('Enabled') : t('Disabled') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ getSectionMeta(section.type).description }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="toggleSection(index)" type="button" :class="section.enabled ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                        <span :class="section.enabled ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                    </button>
                                    <button @click="editSection(index)" type="button" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-gray-600 dark:text-gray-400 hover:text-primary-600 hover:border-primary-300 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                    <button @click="removeSection(index)" :disabled="section.core" type="button" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 text-danger-500 hover:bg-danger-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                </div>
                                </div>
                            </template>
                            <div
                                @pointerenter="onDragOver(form.sections.length)"
                                :class="draggedIndex !== null && dragOverIndex === form.sections.length ? 'h-14 border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 shadow-sm' : draggedIndex !== null ? 'h-8 border-gray-300 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 text-gray-400 dark:text-gray-500' : 'h-3 border-transparent text-transparent'"
                                class="flex items-center justify-center rounded-xl border-2 border-dashed text-xs font-black uppercase tracking-widest transition-all duration-150"
                            >
                                {{ t('Drop section here') }}
                            </div>
                            <div v-if="draggedSection" :style="{ left: `${dragPosition.x + 18}px`, top: `${dragPosition.y + 18}px` }" class="fixed z-[9999] pointer-events-none w-[420px] max-w-[calc(100vw-2rem)] rounded-2xl border-2 border-primary-500 bg-white dark:bg-surface-900 p-4 shadow-2xl shadow-primary-900/20 ring-2 ring-primary-500 ring-dashed">
                                <div class="flex items-center gap-4">
                                    <div class="text-gray-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ getSectionMeta(draggedSection.type).label }}</div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">{{ getSectionMeta(draggedSection.type).description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-4">{{ t('Preview Frame') }}</h2>
                        <div :class="mobilePreview ? 'max-w-[390px]' : 'w-full'" class="mx-auto rounded-3xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-950 overflow-hidden transition-all">
                            <div class="h-8 bg-white dark:bg-surface-900 border-b border-gray-100 dark:border-surface-800 flex items-center gap-1 px-4">
                                <span class="w-2 h-2 rounded-full bg-danger-400"></span>
                                <span class="w-2 h-2 rounded-full bg-warning-400"></span>
                                <span class="w-2 h-2 rounded-full bg-success-400"></span>
                            </div>
                            <div class="p-4 space-y-3 min-h-[360px]">
                                <div v-for="section in form.sections.filter((item) => item.enabled)" :key="`preview_${section.id}`" class="rounded-2xl border border-gray-100 dark:border-surface-800 bg-white dark:bg-surface-900 p-4">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-primary-500 mb-2">{{ getSectionMeta(section.type).label }}</div>
                                    <div class="h-3 bg-gray-100 dark:bg-surface-800 rounded-full mb-2"></div>
                                    <div class="h-3 bg-gray-100 dark:bg-surface-800 rounded-full w-2/3"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-primary-50 dark:bg-primary-900/10 rounded-2xl border border-primary-100 dark:border-primary-900/30 p-6">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-2">{{ t('Publish Behavior') }}</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ t('Saving publishes immediately to the homepage. Use Live Preview after saving to inspect the public page.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="addSectionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Add Homepage Section') }}</h3>
                    <button @click="addSectionModalOpen = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">{{ t('Close') }}</button>
                </div>
                <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button v-for="section in availableSections" :key="section.type" @click="addSection(section.type)" type="button" class="text-left p-5 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-all">
                        <div class="font-bold text-gray-900 dark:text-white">{{ section.label }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">{{ section.description }}</div>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="sectionModalOpen && editingSection" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t(':section Settings', { section: getSectionMeta(editingSection.data.type).label }) }}</h3>
                </div>
                <div class="p-6 overflow-y-auto space-y-5">

                    <!-- Testimonials content source banner -->
                    <a
                        v-if="editingSection.data.type === 'testimonials'"
                        :href="route('admin.testimonials.index')"
                        target="_blank"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors group"
                    >
                        <svg class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ t('Manage Testimonials') }}</span>
                            <span class="text-xs text-primary-500 dark:text-primary-400 ml-2">{{ t('Add, edit, and reorder customer reviews used by this section') }}</span>
                        </div>
                        <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>

                    <!-- FAQ content source banner -->
                    <a
                        v-if="editingSection.data.type === 'faq'"
                        :href="route('admin.faqs.index')"
                        target="_blank"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors group"
                    >
                        <svg class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-bold text-primary-700 dark:text-primary-300">{{ t('Manage FAQs') }}</span>
                            <span class="text-xs text-primary-500 dark:text-primary-400 ml-2">{{ t('Add, categorize, and reorder FAQ entries used by this section') }}</span>
                        </div>
                        <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="(value, key) in editingSection.data.config" :key="key">
                            <template v-if="typeof value === 'boolean'">
                                <label class="flex items-center justify-between gap-4 p-4 rounded-xl bg-gray-50 dark:bg-surface-800 cursor-pointer">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ configLabel(key) }}</span>
                                    <button @click="editingSection.data.config[key] = !value" type="button" :class="value ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                        <span :class="value ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                    </button>
                                </label>
                            </template>
                            <template v-else-if="typeof value === 'number'">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ configLabel(key) }}</label>
                                <input v-model.number="editingSection.data.config[key]" type="number" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                            <template v-else-if="Array.isArray(value) && value.every((item) => typeof item === 'string')">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ configLabel(key) }}</label>
                                <input :value="value.join(', ')" @input="editingSection.data.config[key] = ($event.target as HTMLInputElement).value.split(',').map((item) => item.trim()).filter(Boolean)" @blur="normalizePhrases" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                            <template v-else-if="Array.isArray(value)">
                                <div class="md:col-span-2 rounded-xl border border-gray-100 dark:border-surface-800 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ configLabel(key) }}</label>
                                        <button @click="addListItem(String(key))" type="button" class="text-xs font-bold text-primary-600">{{ t('Add Item') }}</button>
                                    </div>
                                    <div class="space-y-3">
                                        <div v-if="value.length === 0" class="text-xs text-gray-500 dark:text-gray-400">{{ t('No items yet. Add one to display content.') }}</div>
                                        <div v-for="(item, itemIndex) in value" :key="itemIndex" class="rounded-lg bg-gray-50 dark:bg-surface-800 p-3 space-y-2">
                                            <div v-for="(itemValue, itemKey) in item" :key="itemKey">
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">{{ configLabel(itemKey) }}</label>
                                                <input :value="String(item[itemKey] ?? '')" @input="setItemString(item, String(itemKey), ($event.target as HTMLInputElement).value)" type="text" class="w-full bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white">
                                            </div>
                                            <button @click="removeListItem(String(key), itemIndex)" type="button" class="text-xs font-bold text-danger-500">{{ t('Remove item') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ configLabel(key) }}</label>
                                <textarea v-if="String(key).includes('content') || String(key).includes('embed') || String(key).includes('description') || String(key).includes('subheadline')" :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLTextAreaElement).value)" rows="3" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"></textarea>
                                <input v-else :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLInputElement).value)" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end gap-3">
                    <button @click="sectionModalOpen = false" type="button" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 rounded-xl transition-colors">{{ t('Cancel') }}</button>
                    <button @click="saveSectionSettings" type="button" class="px-5 py-2.5 btn-primary text-sm font-bold rounded-xl transition-all shadow-lg shadow-primary-600/20">{{ t('Apply Configuration') }}</button>
                </div>
            </div>
        </div>

        <div v-if="settingsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Homepage General Settings') }}</h3>
                    <button @click="settingsModalOpen = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">{{ t('Close') }}</button>
                </div>
                <div class="p-6 overflow-y-auto space-y-6">
                    <div class="space-y-4">
                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-500">{{ t('SEO') }}</h4>
                        <input v-model="form.settings.seo.meta_title" type="text" :placeholder="t('Meta title')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        <textarea v-model="form.settings.seo.meta_description" rows="3" :placeholder="t('Meta description')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white"></textarea>
                        <input v-model="form.settings.seo.og_image" type="text" :placeholder="t('OG image URL')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-surface-800">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Preloader') }}</span>
                            <input v-model="form.settings.preloader.enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        </label>
                        <input v-model="form.settings.preloader.animation_url" type="text" :placeholder="t('Lottie/GIF URL')" class="bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        <label class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-surface-800">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Scroll to top') }}</span>
                            <input v-model="form.settings.scroll_to_top.enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <select v-model="form.settings.scroll_to_top.position" class="bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                                <option value="right">{{ t('Right') }}</option>
                                <option value="left">{{ t('Left') }}</option>
                            </select>
                            <input v-model.number="form.settings.scroll_to_top.show_after_px" type="number" min="0" max="5000" class="bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-surface-800">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ t('Cookie consent banner') }}</span>
                            <input v-model="form.settings.cookie_consent.enabled" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        </label>
                        <input v-model="form.settings.cookie_consent.message" type="text" :placeholder="t('Cookie message')" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input v-model="form.settings.cookie_consent.accept_text" type="text" :placeholder="t('Accept button text')" class="bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                            <input v-model="form.settings.cookie_consent.policy_url" type="text" :placeholder="t('Cookie policy URL')" class="bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">{{ t('Chat Widget Embed') }}</label>
                        <textarea v-model="form.settings.chat_widget_embed" rows="5" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white font-mono"></textarea>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end">
                    <button @click="settingsModalOpen = false" type="button" class="px-5 py-2.5 btn-primary text-sm font-bold rounded-xl transition-all">{{ t('Done') }}</button>
                </div>
            </div>
        </div>

        <!-- When a site template is active, show a message instead of the builder -->
        <div v-if="!isCustomHomepage" class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2">
                <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-10 text-center">
                    <div class="text-4xl mb-4">🎨</div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">{{ t('Site Template Active') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                        {{ t('The homepage is currently using the') }} <strong>{{ props.availableTemplates.find((template) => template.slug === props.activeHomepageTemplate)?.name ?? props.activeHomepageTemplate }}</strong> {{ t('template.') }}
                        {{ t('Switch to') }} <strong>{{ t('Custom Homepage') }}</strong> {{ t('above to use the drag & drop builder.') }}
                    </p>
                    <a
                        v-if="props.activeHomepageTemplate !== 'default'"
                        :href="route('admin.site-templates.edit', props.activeHomepageTemplate)"
                        class="inline-flex items-center gap-2 mt-4 px-4 py-2 btn-primary rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ t('Edit Template Settings') }}
                    </a>
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
    </AdminLayout>
</template>
