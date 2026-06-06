<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
    tool: any | null
    categories: any[]
    aiModels?: any[]
    reviews?: any[]
}>()

const isEditing = computed(() => !!props.tool)
const activeTab = ref('basic')

const tabs = [
    { key: 'basic', label: t('Basic'), icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z' },
    { key: 'prompts', label: t('Prompts'), icon: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z' },
    { key: 'fields', label: t('Fields'), icon: 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z' },
    { key: 'content', label: t('Content'), icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
    { key: 'seo', label: t('SEO'), icon: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z' },
]

const textLength = (value: unknown) => String(value || '').length

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
    requires_pro: props.tool?.requires_pro ?? false,
    access_level: props.tool?.access_level || 'inherit',

    // Prompts
    prompt_system: props.tool?.prompt_system || '',
    prompt_user: props.tool?.prompt_user || '',
    output_type: props.tool?.output_type || 'markdown',
    model_override: props.tool?.model_override || '',
    max_tokens_override: props.tool?.max_tokens_override || '',
    temperature: props.tool?.temperature ?? 0.7,
    supports_brand_voice: props.tool?.supports_brand_voice ?? true,
    avg_output_tokens: props.tool?.avg_output_tokens || '',

    // Fields
    fields: props.tool?.fields ? (typeof props.tool?.fields === 'string' ? JSON.parse(props.tool?.fields) : props.tool?.fields) : [],

    // Content
    about_content: props.tool?.about_content || '',
    how_it_works: props.tool?.how_it_works ? (typeof props.tool?.how_it_works === 'string' ? JSON.parse(props.tool?.how_it_works) : props.tool?.how_it_works) : [],
    usage_examples: props.tool?.usage_examples ? (typeof props.tool?.usage_examples === 'string' ? JSON.parse(props.tool?.usage_examples) : props.tool?.usage_examples) : [],
    faq_items: props.tool?.faq_items ? (typeof props.tool?.faq_items === 'string' ? JSON.parse(props.tool?.faq_items) : props.tool?.faq_items) : [],
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
    ...props.categories.map((c: any) => ({ value: String(c.id), label: c.name })),
])

const accessLevelOptions = [
    { value: 'inherit', label: 'Inherit' },
    { value: 'public', label: 'Public' },
    { value: 'login_required', label: 'Login Required' },
    { value: 'free_plan', label: 'Free Plan' },
    { value: 'pro_plan', label: 'Pro Plan' },
]

const outputTypeOptions = [
    { value: 'text', label: 'Text' },
    { value: 'markdown', label: 'Markdown' },
    { value: 'html', label: 'HTML' },
    { value: 'code', label: 'Code' },
    { value: 'list', label: 'List' },
    { value: 'json', label: 'JSON' },
]

const modelOptions = computed(() => {
    const models = props.aiModels || []
    return [
        { value: '', label: t('Use default model') },
        ...models.map((m: any) => ({ value: m.slug, label: `${m.name} (${m.provider})` })),
    ]
})

const fieldTypeOptions = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'select', label: 'Select' },
    { value: 'number', label: 'Number' },
    { value: 'toggle', label: 'Toggle' },
    { value: 'slider', label: 'Slider' },
    { value: 'color', label: 'Color' },
    { value: 'tags_input', label: 'Tags Input' },
    { value: 'tone_select', label: 'Tone Select' },
    { value: 'language_select', label: 'Language Select' },
    { value: 'length_select', label: 'Length Select' },
    { value: 'model_select', label: 'Model Select' },
    { value: 'image_upload', label: 'Image Upload' },
    { value: 'file_upload', label: 'File Upload' },
    { value: 'code_input', label: 'Code Input' },
    { value: 'url', label: 'URL' },
    { value: 'date', label: 'Date' },
    { value: 'datetime_local', label: 'Date & Time' },
    { value: 'radio', label: 'Radio' },
    { value: 'multi_select', label: 'Multi Select' },
    { value: 'hidden', label: 'Hidden' },
]

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

const ogImagePreview = ref(props.tool?.og_image ? `/storage/${props.tool.og_image}` : null)

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
    form.fields.push({ name: '', key: '', label: '', type: 'text', required: false, placeholder: '', options: [] })
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
    form.usage_examples.push({ title: '', description: '', output: '' })
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

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.ai.tools.update', props.tool.id))
    } else {
        form.post(route('admin.ai.tools.store'))
    }
}
</script>

<template>
    <Head :title="(isEditing ? t('Edit Tool') : t('Create Tool')) + ' - ' + t('Admin')" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.ai.tools.index')" class="px-2 py-1 rounded-lg bg-surface-200 hover:bg-gray-300 dark:bg-surface-800 dark:hover:bg-surface-700 text-gray-400 transition-colors">
                       <i class="ti ti-chevron-left"></i>
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isEditing ? t('Edit Tool') : t('Create Tool') }}</h1>
                </div>
                <p v-if="isEditing" class="text-sm text-gray-500 dark:text-gray-400 mt-1 ml-11">{{ tool.slug }}</p>
            </div>
            <button @click="submit" :disabled="form.processing" :class="form.processing ? 'opacity-50 cursor-wait' : ''" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-primary-500/20 transition-all">
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                {{ isEditing ? t('Save Changes') : t('Create Tool') }}
            </button>
        </div>

        <!-- Tabs -->
        <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-200 dark:border-surface-800 overflow-hidden">
            <div class="flex border-b border-gray-100 dark:border-surface-800 overflow-x-auto">
                <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[activeTab === tab.key ? 'text-primary-600 dark:text-primary-400 border-primary-500' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-700 dark:hover:text-gray-300']" class="flex items-center gap-2 px-5 py-3.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" /></svg>
                    {{ tab.label }}
                </button>
            </div>

            <div class="p-6 space-y-5">
                <!-- Errors -->
                <div v-if="Object.keys(form.errors).length" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
                    <ul class="list-disc pl-4 space-y-1">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <!-- ═══ TAB: Basic ═══ -->
                <div v-show="activeTab === 'basic'" class="space-y-5">
                    <div class="grid grid-cols-2 gap-5">
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
                    <div class="grid grid-cols-3 gap-5">
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
                    <div class="grid grid-cols-2 gap-5">
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
                            <button
                                type="button"
                                :class="form.is_active ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                @click="form.is_active = !form.is_active"
                            >
                                <span :class="form.is_active ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                :class="form.is_featured ? 'bg-warning-500' : 'bg-gray-200 dark:bg-surface-600'"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                @click="form.is_featured = !form.is_featured"
                            >
                                <span :class="form.is_featured ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Featured') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                :class="form.requires_pro ? 'bg-accent-500' : 'bg-gray-200 dark:bg-surface-600'"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                @click="form.requires_pro = !form.requires_pro"
                            >
                                <span :class="form.requires_pro ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Requires Pro') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: Prompts ═══ -->
                <div v-show="activeTab === 'prompts'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('System Prompt') }}</label>
                        <textarea v-model="form.prompt_system" rows="5" @input="autoExpand" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono resize-none overflow-hidden" :placeholder="t('You are a professional writer...')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t("Instructions for the AI's behavior and persona.") }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('User Prompt Template') }}</label>
                        <textarea v-model="form.prompt_user" rows="5" @input="autoExpand" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono resize-none overflow-hidden" :placeholder="t('Write a blog post about {topic}...')" />
                        <p class="text-xs text-gray-400 mt-1">{{ t('Use') }} <code class="text-primary-500">{field_key}</code> {{ t('for dynamic field placeholders.') }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <AppSelect v-model="form.output_type" :options="outputTypeOptions" :label="t('Output Type')" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Temperature') }}</label>
                            <input v-model.number="form.temperature" type="number" step="0.1" min="0" max="2" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                        </div>
                        <div>
                            <AppSelect v-model="form.model_override" :options="modelOptions" :label="t('Model Override')" :placeholder="t('Use default model')" />
                            <p class="text-xs text-gray-400 mt-1">{{ t('Force a specific AI model for this tool.') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-2">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                :class="form.supports_brand_voice ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                @click="form.supports_brand_voice = !form.supports_brand_voice"
                            >
                                <span :class="form.supports_brand_voice ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Supports Brand Voice') }}</span>
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: Fields ═══ -->
                <div v-show="activeTab === 'fields'" class="space-y-4">
                    <div v-for="(field, i) in form.fields" :key="i" class="p-4 bg-gray-50 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-gray-400 uppercase">{{ t('Field :number', { number: Number(i) + 1 }) }}</span>
                            <button @click="confirmRemoveField(Number(i))" class="p-1 text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 gap-3">
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
                                <button
                                    type="button"
                                    :class="field.required ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'"
                                    class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                    @click="field.required = !field.required"
                                >
                                    <span :class="field.required ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <button @click="addField" class="w-full py-3 border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-xl text-sm font-semibold text-gray-400 hover:text-primary-500 hover:border-primary-300 transition-colors">
                        {{ t('+ Add Field') }}
                    </button>
                </div>

                <!-- ═══ TAB: Content ═══ -->
                <div v-show="activeTab === 'content'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('About Content') }}</label>
                        <textarea v-model="form.about_content" rows="4" @input="autoExpand" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm resize-none overflow-hidden" :placeholder="t('Describe what this tool does...')" />
                    </div>

                    <!-- How It Works -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('How It Works') }}</label>
                        </div>
                        <div v-for="(step, i) in form.how_it_works" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">{{ t('Step :number', { number: Number(i) + 1 }) }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="confirmRemoveStep(Number(i))">{{ t('Remove') }}</button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-[80px_1fr_1fr]">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Step #') }}</label>
                                    <input v-model.number="step.step" type="number" min="1" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Icon') }}</label>
                                    <IconClassSelect v-model="step.icon" :placeholder="t('Choose an icon')" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Title') }}</label>
                                    <input v-model="step.title" type="text" :placeholder="t('Step title')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Description') }}</label>
                                <textarea v-model="step.description" rows="2" @input="autoExpand" :placeholder="t('Short step description')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm resize-none overflow-hidden dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addStep">{{ t('+ Add Step') }}</button>
                    </div>

                    <!-- Usage Examples -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Usage Examples') }}</label>
                        </div>
                        <div v-for="(example, i) in form.usage_examples" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">{{ t('Example :number', { number: Number(i) + 1 }) }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="confirmRemoveExample(Number(i))">{{ t('Remove') }}</button>
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Title') }}</label>
                                <input v-model="example.title" type="text" :placeholder="t('Example title')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Description') }}</label>
                                <textarea v-model="example.description" rows="3" @input="autoExpand" :placeholder="t('Describe the input scenario for this example...')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm resize-none overflow-hidden dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Output') }}</label>
                                <textarea v-model="example.output" rows="4" @input="autoExpand" :placeholder="t('Generated output preview')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm resize-none overflow-hidden dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addExample">{{ t('+ Add Example') }}</button>
                    </div>

                    <!-- FAQs -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('FAQs') }}</label>
                        </div>
                        <div v-for="(faq, i) in form.faq_items" :key="i" class="flex gap-3 mb-3">
                            <div class="flex-1 space-y-2">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Question') }}</label>
                                    <input v-model="faq.question" type="text" :placeholder="t('FAQ question')" class="w-full px-3 py-2 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ t('Answer') }}</label>
                                    <textarea v-model="faq.answer" rows="2" @input="autoExpand" :placeholder="t('FAQ answer')" class="w-full px-3 py-2 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg text-sm resize-none overflow-hidden" />
                                </div>
                            </div>
                            <button @click="confirmRemoveFaq(Number(i))" class="p-1 text-gray-400 hover:text-red-500 self-start mt-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <button @click="addFaq" class="text-sm font-semibold text-primary-600 hover:text-primary-700">{{ t('+ Add FAQ') }}</button>
                    </div>

                    <!-- Content section toggles -->
                    <div class="flex flex-wrap items-center gap-x-8 gap-y-3 pt-2">
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_about ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_about = !form.show_about">
                                <span :class="form.show_about ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show About') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_how_it_works ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_how_it_works = !form.show_how_it_works">
                                <span :class="form.show_how_it_works ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show How It Works') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_usage_examples ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_usage_examples = !form.show_usage_examples">
                                <span :class="form.show_usage_examples ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Examples') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_faqs ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_faqs = !form.show_faqs">
                                <span :class="form.show_faqs ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show FAQs') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_reviews ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_reviews = !form.show_reviews">
                                <span :class="form.show_reviews ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Reviews') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" :class="form.show_related_tools ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-600'" class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors" @click="form.show_related_tools = !form.show_related_tools">
                                <span :class="form.show_related_tools ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none ml-0.5 mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" />
                            </button>
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
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">{{ t('OG Image') }}</label>
                        <div v-if="form.og_image || ogImagePreview" class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800 flex items-center gap-3">
                            <img :src="ogImagePreview || `/storage/${form.og_image}`" alt="OG Image" class="h-16 w-auto max-w-[200px] rounded object-cover" />
                            <button type="button" @click="removeOgImage" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                        </div>
                        <input type="file" accept="image/png,image/jpeg,image/webp" @change="setOgImage" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400" />
                        <p class="text-xs text-gray-400 mt-1">{{ t('Recommended: 1200×630px, PNG or JPEG, max 4MB') }}</p>
                        <span v-if="form.errors.og_image_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.og_image_file }}</span>
                    </div>
                </div>
            </div>
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
