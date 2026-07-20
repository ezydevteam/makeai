<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import IconClassSelect from '@/Components/Admin/IconClassSelect.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const page = usePage()

const props = defineProps<{
    tool: any | null
    categories: any[]
    aiModels?: any[]
    reviews?: any[]
    accessLevels?: Array<{ value: string; label: string; description?: string }>
    integrations?: Array<{ slug: string; name: string }>
}>()

const isEditing = computed(() => !!props.tool)
const activeTab = ref('basic')
const activeContentSection = ref('about')

const tabs = computed(() => {
    const list = [
        { key: 'basic', label: t('Basic'), icon: 'ti ti-info-circle' },
    ]
    if (props.tool?.type !== 'rag') {
        list.push(
            { key: 'prompts', label: t('Prompts'), icon: 'ti ti-message-2-code' },
            { key: 'fields', label: t('Fields'), icon: 'ti ti-forms' }
        )
    }
    list.push(
        { key: 'content', label: t('Content'), icon: 'ti ti-file-text' },
        { key: 'seo', label: t('SEO'), icon: 'ti ti-search' }
    )
    return list
})

const tabDescriptions: Record<string, string> = {
    basic: t('Set the tool identity, visibility, and access rules.'),
    prompts: t('Control model behavior, prompt templates, and output settings.'),
    fields: t('Design the dynamic form fields shown to users.'),
    content: t('Manage about blocks, steps, examples, and FAQs.'),
    seo: t('Configure search metadata and social preview assets.'),
}

const pageTitle = computed(() => (isEditing.value ? t('Edit Tool') : t('Create Tool')))

const pageDescription = computed(() => (
    isEditing.value
        ? t('Update tool settings, prompts, fields, content, and search metadata.')
        : t('Create a new AI tool with access rules, prompts, dynamic fields, and SEO content.')
))

const textLength = (value: unknown) => String(value || '').length

const ensureArray = (val: any) => {
    if (!val) return []
    if (Array.isArray(val)) return val
    if (typeof val === 'string') {
        try {
            const parsed = JSON.parse(val)
            return Array.isArray(parsed) ? parsed : Object.values(parsed)
        } catch {
            return []
        }
    }
    if (typeof val === 'object') {
        return Object.values(val)
    }
    return []
}

const mapUsageExamplesOnLoad = (examples: any) => {
    return ensureArray(examples).map((ex: any) => {
        let inputText = ''
        if (ex.input && typeof ex.input === 'object') {
            inputText = Object.entries(ex.input)
                .map(([key, val]) => `${key}: ${val}`)
                .join('\n')
        }
        return {
            title: ex.title || '',
            input_text: inputText,
            output: ex.output || '',
        }
    })
}

const form = useForm({
    // Basic
    name: props.tool?.name || '',
    slug: props.tool?.slug || '',
    description: props.tool?.description || '',
    category_id: props.tool?.category_id || '',
    icon: props.tool?.icon || '',
    color: props.tool?.color || '#6366f1',
    sort_order: props.tool?.sort_order || 0,
    is_active: props.tool?.is_active ?? true,
    is_featured: props.tool?.is_featured ?? false,
    show_header: props.tool?.show_header ?? true,
    show_footer: props.tool?.show_footer ?? true,
    is_embeddable: props.tool?.is_embeddable ?? true,
    access_level: props.tool?.access_level || 'inherit',

    // Prompts
    prompt_system: props.tool?.prompt_system || '',
    prompt_user: props.tool?.prompt_user || '',
    output_type: props.tool?.output_type || 'markdown',
    model_override: props.tool?.model_override || '',
    generation_mode: props.tool?.generation_mode || 'llm',
    integration_slug: props.tool?.integration_slug || '',
    max_tokens_override: props.tool?.max_tokens_override || '',
    temperature: props.tool?.temperature ?? 0.7,
    supports_brand_voice: props.tool?.supports_brand_voice ?? true,
    max_variants: props.tool?.max_variants ?? 1,
    show_improve: props.tool?.show_improve ?? true,
    avg_output_tokens: props.tool?.avg_output_tokens || '',

    // Fields
    // RAG tools store `fields` as a CONFIG OBJECT ({accept, multi_file, max_file_mb,
    // source_type}), not a list of form-field definitions — and their field editor
    // tab is hidden (v-if type !== 'rag' below). Mapping that object as field defs
    // called .map on a non-array and crashed the editor on load for every RAG tool.
    // Keep the config object as-is so it round-trips on save; only text tools get
    // the field-definition transform.
    fields: props.tool?.type === 'rag'
        ? (props.tool?.fields ?? {})
        : ensureArray(props.tool?.fields).map((f: any) => ({
            ...f,
            options_string: Array.isArray(f.options) ? f.options.join(', ') : (typeof f.options === 'string' ? f.options : '')
        })),

    // Content
    about_content: props.tool?.about_content || '',
    how_it_works: ensureArray(props.tool?.how_it_works),
    usage_examples: mapUsageExamplesOnLoad(props.tool?.usage_examples),
    faq_items: ensureArray(props.tool?.faq_items),
    show_about: props.tool?.show_about ?? true,
    show_how_it_works: props.tool?.show_how_it_works ?? true,
    show_usage_examples: props.tool?.show_usage_examples ?? true,
    show_faqs: props.tool?.show_faqs ?? true,
    show_reviews: props.tool?.show_reviews ?? true,
    show_related_tools: props.tool?.show_related_tools ?? true,

    // SEO
    meta_title: props.tool?.meta_title || '',
    meta_description: props.tool?.meta_description || '',
    og_image: props.tool?.og_image || '',
    og_image_file: null as File | null,
})

// ─── Select options ───────────────────────────

const categoryOptions = computed(() => [
    { value: '', label: t('None') },
    ...props.categories.map((c: any) => ({ value: c.id, label: c.name })),
])

const accessLevelOptions = computed(() => {
    if (props.accessLevels) {
        return props.accessLevels
    }

    // Fallback: only include premium if isProAvailable
    const isProAvailable = page.props.isProAvailable ?? false
    const options = [
        { value: 'inherit', label: t('Inherit (Default)') },
        { value: 'guest', label: t('Guest (Free)') },
        { value: 'login', label: t('Login Required') },
    ]

    if (isProAvailable) {
        options.push({ value: 'premium', label: t('Premium (Any Plan)') })
    }

    return options
})

const outputTypeOptions = [
    { value: 'text', label: t('Text') },
    { value: 'markdown', label: t('Markdown') },
    { value: 'html', label: t('HTML') },
    { value: 'code', label: t('Code') },
    { value: 'list', label: t('List') },
    { value: 'json', label: t('JSON') },
]

const modelOptions = computed(() => {
    const models = props.aiModels || []
    return [
        { value: '', label: t('Use default model') },
        ...models.map((m: any) => ({ value: m.slug, label: `${m.name} (${m.provider})` })),
    ]
})

const generationModeOptions = [
    { value: 'llm', label: t('LLM only') },
    { value: 'integration_llm_fallback', label: t('Integration + LLM fallback') },
    { value: 'integration', label: t('Integration only') },
]

const integrationOptions = computed(() => [
    ...(props.integrations || []).map((i) => ({ value: i.slug, label: i.name })),
])

const fieldTypeOptions = [
    { value: 'text', label: t('Text') },
    { value: 'textarea', label: t('Textarea') },
    { value: 'select', label: t('Select') },
    { value: 'number', label: t('Number') },
    { value: 'toggle', label: t('Toggle') },
    { value: 'slider', label: t('Slider') },
    { value: 'color', label: t('Color') },
    { value: 'tags_input', label: t('Tags Input') },
    { value: 'tone_select', label: t('Tone Select') },
    { value: 'language_select', label: t('Language Select') },
    { value: 'length_select', label: t('Length Select') },
    { value: 'audience_select', label: t('Audience Select') },
    { value: 'model_select', label: t('Model Select') },
    { value: 'image_upload', label: t('Image Upload') },
    { value: 'file_upload', label: t('File Upload') },
    { value: 'code_input', label: t('Code Input') },
    { value: 'url', label: t('URL') },
    { value: 'date', label: t('Date') },
    { value: 'datetime_local', label: t('Date & Time') },
    { value: 'radio', label: t('Radio') },
    { value: 'multi_select', label: t('Multi Select') },
    { value: 'hidden', label: t('Hidden') },
]

const findOptionLabel = (options: { value: string; label: string }[], value: unknown, fallback: string) => {
    return options.find((option) => option.value === value)?.label || fallback
}

const activeTabDescription = computed(() => tabDescriptions[activeTab.value] || t('Manage this section.'))

// ─── Auto-slug ────────────────────────────────

const autoSlug = () => {
    if (!isEditing.value) {
        form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
    }
}

// ─── Auto-expand textarea ─────────────────────

const autoExpand = (e: Event) => {
    const el = e.target as HTMLTextAreaElement
    el.style.height = 'auto'
    el.style.height = el.scrollHeight + 'px'
}

// ─── OG Image ─────────────────────────────────

const ogImagePreview = ref(props.tool?.og_image ? mediaUrl(props.tool.og_image) : null)

const setOgImage = (e: Event) => {
    const input = e.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    form.og_image_file = file
    if (file) {
        ogImagePreview.value = URL.createObjectURL(file)
    }
}

const removeOgImage = () => {
    form.og_image_file = null
    form.og_image = ''
    ogImagePreview.value = null
}

// ─── Field management ─────────────────────────

const addField = () => {
    form.fields.push({ name: '', key: '', label: '', type: 'text', required: false, placeholder: '', options: [], options_string: '' })
}

const fieldRemoveIndex = ref<number | null>(null)
const confirmRemoveField = (index: number) => { fieldRemoveIndex.value = index }
const executeRemoveField = () => {
    if (fieldRemoveIndex.value !== null) {
        form.fields.splice(fieldRemoveIndex.value, 1)
        fieldRemoveIndex.value = null
    }
}

// ─── FAQ management ───────────────────────────

const addFaq = () => { form.faq_items.push({ question: '', answer: '' }) }
const faqRemoveIndex = ref<number | null>(null)
const confirmRemoveFaq = (index: number) => { faqRemoveIndex.value = index }
const executeRemoveFaq = () => {
    if (faqRemoveIndex.value !== null) {
        form.faq_items.splice(faqRemoveIndex.value, 1)
        faqRemoveIndex.value = null
    }
}

// ─── How It Works ─────────────────────────────

const addStep = () => {
    form.how_it_works.push({ step: form.how_it_works.length + 1, icon: 'ti-forms', title: '', description: '' })
}
const stepRemoveIndex = ref<number | null>(null)
const confirmRemoveStep = (index: number) => { stepRemoveIndex.value = index }
const executeRemoveStep = () => {
    if (stepRemoveIndex.value !== null) {
        form.how_it_works.splice(stepRemoveIndex.value, 1)
        form.how_it_works.forEach((step: any, i: number) => { step.step = i + 1 })
        stepRemoveIndex.value = null
    }
}

// ─── Usage Examples ───────────────────────────

const addExample = () => {
    form.usage_examples.push({ title: '', input_text: '', output: '' })
}
const exampleRemoveIndex = ref<number | null>(null)
const confirmRemoveExample = (index: number) => { exampleRemoveIndex.value = index }
const executeRemoveExample = () => {
    if (exampleRemoveIndex.value !== null) {
        form.usage_examples.splice(exampleRemoveIndex.value, 1)
        exampleRemoveIndex.value = null
    }
}

// ─── Submit ───────────────────────────────────

const updateFieldOptions = (field: { options_string?: string; options?: string[] }) => {
    field.options = (field.options_string || '').split(',').map((s) => s.trim()).filter(Boolean)
}

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.ai.tools.update', props.tool.id))
    } else {
        form.post(route('admin.ai.tools.store'))
    }
}

</script>

<template>
    <Head :title="pageTitle" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ pageTitle }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ pageDescription }}</p>
            </div>

            <div class="shrink-0 flex items-center gap-3">
                <Link
                    :href="route('admin.ai.tools.index')"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700  transition  hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    :class="form.processing ? 'cursor-wait opacity-50' : ''"
                    class="inline-flex items-center gap-2 btn-primary-admin"
                >
                    <i v-if="form.processing" class="ti ti-loader-2 animate-spin text-base"></i>
                    <i v-else-if="!isEditing" class="ti ti-plus text-base"></i>
                    <i v-else class="ti ti-device-floppy text-base"></i>
                    {{ isEditing ? t('Save Changes') : t('Create Tool') }}
                </button>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                        <h6 class="text-base mb-0 font-semibold text-gray-700 dark:text-gray-200">
                            {{ form.name || t('Untitled Tool') }}
                        </h6>
                        <p class="mb-0 text-xs font-medium lowercase tracking-[0.14em] text-gray-400 dark:text-gray-500">
                            {{ form.slug || t('no-slug') }}
                        </p>
                    </div>
                    <div class="grid gap-2 p-3 sm:grid-cols-2 xl:grid-cols-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            @click="activeTab = tab.key"
                            :class="activeTab === tab.key
                                ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300'
                                : 'border-transparent text-gray-600 hover:border-gray-200 hover:bg-gray-50 dark:text-gray-300 dark:hover:border-surface-700 dark:hover:bg-surface-800'"
                            class="flex w-full items-center gap-3 rounded-xl border px-4 py-2 text-left text-sm font-medium transition"
                        >
                            <span
                                :class="activeTab === tab.key
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                    : 'bg-gray-100 text-gray-500 dark:bg-surface-800 dark:text-gray-400'"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-base"
                            >
                                <i :class="tab.icon"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate">{{ tab.label }}</span>
                            </span>
                        </button>
                    </div>
                </section>

            </aside>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">
                            <i :class="[tabs.find((tab) => tab.key === activeTab)?.icon || 'ti ti-settings', 'text-lg']"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ tabs.find((tab) => tab.key === activeTab)?.label || t('Section') }}
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ activeTabDescription }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 p-6">
                    <!-- Errors -->
                    <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                        <ul class="list-disc space-y-1 pl-4">
                            <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                        </ul>
                    </div>

                <!-- ═══ TAB: Basic ═══ -->
                <div v-show="activeTab === 'basic'" class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Name') }} *</label>
                            <input v-model="form.name" @input="autoSlug" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="t('Blog Post Writer')" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Slug') }} *</label>
                            <input v-model="form.slug" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Description') }} *</label>
                        <textarea v-model="form.description" rows="2" @input="autoExpand" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm resize-none overflow-hidden" :placeholder="t('Generate professional blog posts...')" />
                    </div>
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <AppSelect v-model="form.category_id" :options="categoryOptions" :label="t('Category')" :placeholder="t('Select a category')" live-search />
                        </div>
                        <div>
                            <IconClassSelect v-model="form.icon" :label="t('Icon')" />
                        </div>
                        <div>
                            <AppColorPicker v-model="form.color" :label="t('Color')" />
                        </div>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Sort Order') }}</label>
                            <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                        </div>
                        <div>
                            <AppSelect v-model="form.access_level" :options="accessLevelOptions" :label="t('Access Level')" />
                        </div>
                    </div>

                    <!-- Toggle Switches (switch first, then label) -->
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-2">
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.is_active" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.is_featured" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Featured') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_header" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Site Header') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_footer" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Site Footer') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.is_embeddable" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Embeddable') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: Prompts ═══ -->
                <div v-if="props.tool?.type !== 'rag'" v-show="activeTab === 'prompts'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('System Prompt') }}</label>
                        <textarea v-model="form.prompt_system" rows="10" class="max-h-[420px] min-h-[220px] w-full overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800" :placeholder="t('You are a professional writer...')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t("Instructions for the AI's behavior and persona.") }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('User Prompt Template') }}</label>
                        <textarea v-model="form.prompt_user" rows="10" class="max-h-[420px] min-h-[220px] w-full overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800" :placeholder="t('Write a blog post about {topic}...')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t('Use') }} <code class="text-primary-500">{field_key}</code> {{ t('for dynamic field placeholders.') }}</p>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <AppSelect v-model="form.output_type" :options="outputTypeOptions" :label="t('Output Type')" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Temperature') }}</label>
                            <input v-model.number="form.temperature" type="number" step="0.1" min="0" max="2" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                        </div>
                        <div>
                            <AppSelect v-model="form.model_override" :options="modelOptions" :label="t('Model Override')" :placeholder="t('Use default model')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('Force a specific AI model for this tool. Also the LLM-fallback model below.') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <AppSelect v-model="form.generation_mode" :options="generationModeOptions" :label="t('Generation Source')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('Run on an integration, the LLM, or the integration with LLM fallback.') }}</p>
                        </div>
                        <div v-if="form.generation_mode !== 'llm'">
                            <AppSelect v-model="form.integration_slug" :options="integrationOptions" :label="t('Integration')" :placeholder="t('Select an integration')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('When unavailable, falls back to the Model Override (or global default).') }}</p>
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Max Tokens Override') }}</label>
                            <input v-model.number="form.max_tokens_override" type="number" min="100" max="128000" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="t('Leave empty for global default')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('Override the global max tokens limit for this tool.') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Avg Output Tokens') }}</label>
                            <input v-model.number="form.avg_output_tokens" type="number" min="50" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="t('Average tokens for cost estimation')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('Used for credit cost estimation on the frontend.') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-2">
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.supports_brand_voice" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Supports Brand Voice') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch
                                :model-value="form.max_variants > 1"
                                @update:model-value="form.max_variants = form.max_variants > 1 ? 1 : 3"
                            />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Variations') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_improve" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Improve Button') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: Fields ═══ -->
                <div v-if="props.tool?.type !== 'rag'" v-show="activeTab === 'fields'" class="space-y-4">
                    <div v-for="(field, i) in form.fields" :key="i" class="p-4 bg-gray-50 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-gray-400 uppercase">{{ t('Field :number', { number: Number(i) + 1 }) }}</span>
                            <button type="button" @click="confirmRemoveField(Number(i))" class="p-1 text-gray-400 hover:text-red-500 transition-colors" :title="t('Delete field')">
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Key') }}</label>
                                <input v-model="field.name" @input="field.key = field.name" type="text" :placeholder="t('field_key')" class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Label') }}</label>
                                <input v-model="field.label" type="text" :placeholder="t('Field Label')" class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Type') }}</label>
                                <AppSelect v-model="field.type" :options="fieldTypeOptions" :placeholder="t('Type')" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Required') }}</label>
                                <AppSwitch v-model="field.required" class="mt-1" />
                            </div>
                        </div>

                        <!-- Options for select, radio, multi_select, audience_select, and language_select -->
                        <div v-if="['select', 'radio', 'multi_select', 'audience_select', 'language_select'].includes(field.type)" class="mt-4">
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                {{ t('Options (comma-separated)') }}
                                <span v-if="['audience_select', 'language_select'].includes(field.type)" class="text-gray-400 font-normal"> ({{ t('leave blank to use default') }})</span>
                            </label>
                            <textarea
                                v-model="field.options_string"
                                rows="2"
                                :placeholder="t('Option 1, Option 2, Option 3')"
                                class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm resize-y"
                                @input="updateFieldOptions(field)"
                            />
                        </div>

                        <!-- Limits for slider -->
                        <div v-if="field.type === 'slider'" class="mt-4 grid grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ t('Lower Limit (Min)') }}</label>
                                <input
                                    v-model="field.min"
                                    type="text"
                                    placeholder="0 or 10%"
                                    class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ t('Upper Limit (Max)') }}</label>
                                <input
                                    v-model="field.max"
                                    type="text"
                                    placeholder="1 or 100%"
                                    class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ t('Step') }}</label>
                                <input
                                    v-model="field.step"
                                    type="text"
                                    placeholder="0.1 or 1"
                                    class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">{{ t('Default Value') }}</label>
                                <input
                                    v-model="field.default"
                                    type="text"
                                    placeholder="0.5 or 50%"
                                    class="w-full px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm"
                                />
                            </div>
                        </div>
                    </div>
                    <button @click="addField" class="w-full py-3 border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-xl text-sm font-semibold text-gray-400 hover:text-primary-500 hover:border-primary-300 transition-colors">
                        {{ t('+ Add Field') }}
                    </button>
                </div>

                <!-- ═══ TAB: Content ═══ -->
                <div v-show="activeTab === 'content'" class="space-y-5">
                    <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-surface-800">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeContentSection === 'about' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-gray-200'"
                            @click="activeContentSection = 'about'"
                        >
                            {{ t('About') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeContentSection === 'steps' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-gray-200'"
                            @click="activeContentSection = 'steps'"
                        >
                            {{ t('How It Works') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeContentSection === 'examples' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-gray-200'"
                            @click="activeContentSection = 'examples'"
                        >
                            {{ t('Examples') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeContentSection === 'faqs' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-gray-200'"
                            @click="activeContentSection = 'faqs'"
                        >
                            {{ t('FAQs') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="activeContentSection === 'visibility' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-surface-800 dark:hover:text-gray-200'"
                            @click="activeContentSection = 'visibility'"
                        >
                            {{ t('Visibility') }}
                        </button>
                    </div>

                    <div v-show="activeContentSection === 'about'">
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('About Content') }}</label>
                        <textarea v-model="form.about_content" rows="4" @input="autoExpand" class="w-full resize-none overflow-hidden rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800" :placeholder="t('Describe what this tool does...')" />
                    </div>

                    <div v-show="activeContentSection === 'steps'">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('How It Works') }}</label>
                        </div>
                        <div v-for="(step, i) in form.how_it_works" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">{{ t('Step :number', { number: Number(i) + 1 }) }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="confirmRemoveStep(Number(i))">{{ t('Remove') }}</button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-[80px_1fr_1fr]">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Step #') }}</label>
                                    <input v-model.number="step.step" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Icon') }}</label>
                                    <IconClassSelect v-model="step.icon" :placeholder="t('Choose an icon')" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Title') }}</label>
                                    <input v-model="step.title" type="text" :placeholder="t('Step title')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Description') }}</label>
                                <textarea v-model="step.description" rows="2" @input="autoExpand" :placeholder="t('Short step description')" class="w-full resize-none overflow-hidden rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addStep">{{ t('+ Add Step') }}</button>
                    </div>

                    <div v-show="activeContentSection === 'examples'">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Usage Examples') }}</label>
                        </div>
                        <div v-for="(example, i) in form.usage_examples" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">{{ t('Example :number', { number: Number(i) + 1 }) }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="confirmRemoveExample(Number(i))">{{ t('Remove') }}</button>
                            </div>
                            <div class="mb-3">
                                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Title') }}</label>
                                <input v-model="example.title" type="text" :placeholder="t('Example title')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                            <div class="mb-3">
                                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Input') }}</label>
                                <textarea v-model="example.input_text" rows="3" @input="autoExpand" :placeholder="t('field_key: value\nanother_field: another value')" class="w-full resize-none overflow-hidden rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Enter each field on a new line using field_key: value (e.g., topic: artificial intelligence).') }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Output') }}</label>
                                <textarea v-model="example.output" rows="4" @input="autoExpand" :placeholder="t('Generated output preview')" class="w-full resize-none overflow-hidden rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ t('Enter the expected AI output preview for this example.') }}</p>
                            </div>
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addExample">{{ t('+ Add Example') }}</button>
                    </div>

                    <div v-show="activeContentSection === 'faqs'">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('FAQs') }}</label>
                        </div>
                        <div v-for="(faq, i) in form.faq_items" :key="i" class="mb-3 flex gap-3">
                            <div class="flex-1 space-y-2">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Question') }}</label>
                                    <input v-model="faq.question" type="text" :placeholder="t('FAQ question')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Answer') }}</label>
                                    <textarea v-model="faq.answer" rows="2" @input="autoExpand" :placeholder="t('FAQ answer')" class="w-full resize-none overflow-hidden rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800" />
                                </div>
                            </div>
                            <button @click="confirmRemoveFaq(Number(i))" class="mt-2 self-start p-1 text-gray-400 hover:text-red-500">
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                        <button @click="addFaq" class="text-sm font-semibold text-primary-600 hover:text-primary-700">{{ t('+ Add FAQ') }}</button>
                    </div>

                    <div v-show="activeContentSection === 'visibility'" class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-2">
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_about" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show About') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_how_it_works" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show How It Works') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_usage_examples" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Examples') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_faqs" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show FAQs') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_reviews" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Reviews') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <AppSwitch v-model="form.show_related_tools" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Related') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: SEO ═══ -->
                <div v-show="activeTab === 'seo'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Meta Title') }}</label>
                        <input v-model="form.meta_title" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="t('Leave empty for auto-generated')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t(':count / 60 characters', { count: textLength(form.meta_title) }) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Meta Description') }}</label>
                        <textarea v-model="form.meta_description" rows="2" @input="autoExpand" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm resize-none overflow-hidden" :placeholder="t('Leave empty for auto-generated')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t(':count / 155 characters', { count: textLength(form.meta_description) }) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Open Graph Image') }}</label>
                        <div v-if="form.og_image || ogImagePreview" class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800 flex items-center gap-3">
                            <img :src="ogImagePreview || mediaUrl(form.og_image)" :alt="t('OG Image')" class="h-16 w-auto max-w-[200px] rounded object-cover" />
                            <button type="button" @click="removeOgImage" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                        </div>
                        <input type="file" accept="image/png,image/jpeg,image/webp" @change="setOgImage" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400" />
                        <p class="text-xs text-gray-400 mt-1">{{ t('Recommended: 1200×630px, PNG or JPEG, max 4MB') }}</p>
                        <span v-if="form.errors.og_image_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.og_image_file }}</span>
                    </div>
                </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Confirm Modals -->
    <ActionConfirmModal
        :open="fieldRemoveIndex !== null"
        :title="t('Remove Field')"
        :message="t('Are you sure you want to remove this field?')"
        :confirm-label="t('Remove')"
        variant="danger"
        @cancel="fieldRemoveIndex = null"
        @confirm="executeRemoveField"
    />
    <ActionConfirmModal
        :open="faqRemoveIndex !== null"
        :title="t('Remove FAQ')"
        :message="t('Are you sure you want to remove this FAQ?')"
        :confirm-label="t('Remove')"
        variant="danger"
        @cancel="faqRemoveIndex = null"
        @confirm="executeRemoveFaq"
    />
    <ActionConfirmModal
        :open="stepRemoveIndex !== null"
        :title="t('Remove Step')"
        :message="t('Are you sure you want to remove this step?')"
        :confirm-label="t('Remove')"
        variant="danger"
        @cancel="stepRemoveIndex = null"
        @confirm="executeRemoveStep"
    />
    <ActionConfirmModal
        :open="exampleRemoveIndex !== null"
        :title="t('Remove Example')"
        :message="t('Are you sure you want to remove this usage example?')"
        :confirm-label="t('Remove')"
        variant="danger"
        @cancel="exampleRemoveIndex = null"
        @confirm="executeRemoveExample"
    />
</template>
