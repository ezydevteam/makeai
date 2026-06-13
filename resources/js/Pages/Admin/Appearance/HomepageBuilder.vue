<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { VueDraggable } from 'vue-draggable-plus'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

type SectionType = 'hero' | 'features' | 'tools_showcase' | 'how_it_works' | 'pricing' | 'testimonials' | 'faq' | 'stats_bar' | 'cta_banner' | 'latest_posts' | 'newsletter' | 'integrations' | 'custom_html' | 'template_grid'

declare const route: (name: string, params?: string | number | Record<string, string | number>) => string

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

const form = useForm<HomepageConfig>({
    sections: props.config.sections,
    settings: props.config.settings,
})

const sectionCatalog: SectionMeta[] = [
    { type: 'hero', label: t('Hero Section'), description: t('Headline, CTAs, hero media, trust badges, and counters.'), icon: 'ti ti-layout-navbar' },
    { type: 'features', label: t('Features Section'), description: t('Feature cards with icons, descriptions, and optional CTA.'), icon: 'ti ti-layout-grid' },
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

const availableSections = computed(() => sectionCatalog.filter((section) => props.sectionTypes.includes(section.type)))

const availableGridTemplates = computed(() =>
  (props.gridTemplates && props.gridTemplates.length > 0)
    ? props.gridTemplates
    : props.availableTemplates.filter(t => t.slug !== 'ai-chatbot')
)

const hiddenConfigKeys: Record<string, string[]> = {
  template_grid: ['template_slug'],
}

const isHiddenConfigKey = (type: SectionType, key: string): boolean => {
  return (hiddenConfigKeys[type] ?? []).includes(key)
}

const enabledSectionsCount = computed(() => form.sections.filter((section) => section.enabled).length)

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

const requestResetToDefaults = () => {
    resetConfirmOpen.value = true
}

const resetToDefaults = () => {
    form.sections = JSON.parse(JSON.stringify(props.config.sections))
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
        if (Array.isArray(data.sections)) form.sections = data.sections
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
    sectionModalOpen.value = true
}

const saveSectionSettings = () => {
    if (editingSection.value === null) return
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

    if (type === 'template_grid') {
        return {
            title,
            subtitle: '',
            template_slug: '',
            max_items: 12,
            show_filter: true,
        }
    }

    if (type === 'all_tools') {
        return {
            title,
            subtitle: t('Find the perfect AI tool for any task.'),
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

        <div v-if="addSectionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Add Homepage Section') }}</h3>
                    <button @click="addSectionModalOpen = false" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-400 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800" :aria-label="t('Close')">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button v-for="section in availableSections" :key="section.type" @click="addSection(section.type)" type="button" class="text-left p-5 rounded-2xl border border-gray-100 dark:border-surface-700 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-all">
                        <div class="font-bold text-gray-900 dark:text-white">{{ section.label }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">{{ section.description }}</div>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="sectionModalOpen && editingSection" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="sectionModalOpen = false">
            <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="border-b border-gray-100 bg-gradient-to-r from-primary-50 via-white to-secondary-50 px-6 py-4 dark:border-surface-800 dark:from-primary-900/20 dark:via-surface-900 dark:to-secondary-900/10">
                    <div class="flex items-start justify-between gap-4">
                        <div class="max-w-2xl">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ t(':section Settings', { section: getSectionMeta(editingSection.data.type).label }) }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ getSectionMeta(editingSection.data.type).description }}</p>
                        </div>
                        <button @click="sectionModalOpen = false" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-400 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800" :aria-label="t('Close')">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="space-y-5 overflow-y-auto p-6">

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

                    <!-- Template grid: template picker -->
                    <div v-if="editingSection.data.type === 'template_grid'" class="rounded-xl border border-primary-100 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-900/20 mb-2">
                      <p class="text-sm text-primary-700 dark:text-primary-300 mb-3">{{ t('Select which template to embed. Template tools and their filtering UI will appear on the homepage.') }}</p>
                      <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Template') }}</label>
                      <select
                        :value="String(editingSection.data.config.template_slug ?? '')"
                        @input="setConfigString('template_slug', ($event.target as HTMLSelectElement).value)"
                        class="w-full bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
                      >
                        <option value="" disabled>{{ t('Choose a template...') }}</option>
                        <option v-for="tpl in availableGridTemplates" :key="tpl.slug" :value="tpl.slug">{{ tpl.name }}</option>
                      </select>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-for="(value, key) in editingSection.data.config" :key="key" v-show="!isHiddenConfigKey(editingSection.data.type, String(key))" :class="Array.isArray(value) && !value.every((item) => typeof item === 'string') ? 'md:col-span-2' : ''" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                            <template v-if="typeof value === 'boolean'">
                                <label class="flex items-center justify-between gap-4 rounded-xl bg-gray-50 p-4 dark:bg-surface-800 cursor-pointer">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ configLabel(key) }}</span>
                                    <button @click="editingSection.data.config[key] = !value" type="button" :class="value ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                        <span :class="value ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                    </button>
                                </label>
                            </template>
                            <template v-else-if="typeof value === 'number'">
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <input v-model.number="editingSection.data.config[key]" type="number" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                            <template v-else-if="Array.isArray(value) && value.every((item) => typeof item === 'string')">
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <input :value="value.join(', ')" @input="editingSection.data.config[key] = ($event.target as HTMLInputElement).value.split(',').map((item) => item.trim()).filter(Boolean)" @blur="normalizePhrases" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                            <template v-else-if="Array.isArray(value)">
                                <div class="rounded-xl border border-gray-100 p-4 dark:border-surface-800 md:col-span-2">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ configLabel(key) }}</label>
                                        <button @click="addListItem(String(key))" type="button" class="inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-300 dark:hover:bg-primary-900/30">
                                            <i class="ti ti-plus text-xs"></i>
                                            {{ t('Add Item') }}
                                        </button>
                                    </div>
                                    <div class="space-y-3">
                                        <div v-if="value.length === 0" class="text-xs text-gray-500 dark:text-gray-400">{{ t('No items yet. Add one to display content.') }}</div>
                                        <div v-for="(item, itemIndex) in value" :key="itemIndex" class="rounded-xl border border-gray-100 bg-gray-50 p-3 space-y-2 dark:border-surface-800 dark:bg-surface-800">
                                            <div v-for="(itemValue, itemKey) in item" :key="itemKey">
                                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">{{ configLabel(itemKey) }}</label>
                                                <input :value="String(item[itemKey] ?? '')" @input="setItemString(item, String(itemKey), ($event.target as HTMLInputElement).value)" type="text" class="w-full bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white">
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
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ configLabel(key) }}</label>
                                <textarea v-if="String(key).includes('content') || String(key).includes('embed') || String(key).includes('description') || String(key).includes('subheadline')" :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLTextAreaElement).value)" rows="3" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"></textarea>
                                <input v-else :value="String(editingSection.data.config[key] ?? '')" @input="setConfigString(String(key), ($event.target as HTMLInputElement).value)" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all">
                            </template>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 p-6 dark:border-surface-700 dark:bg-surface-800">
                    <button @click="sectionModalOpen = false" type="button" class="rounded-xl px-5 py-2.5 text-sm font-bold text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-700">{{ t('Cancel') }}</button>
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
