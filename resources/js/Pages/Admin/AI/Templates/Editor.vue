<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    template: any | null
    categories: any[]
    reviews?: any[]
}>()

const isEditing = computed(() => !!props.template)
const activeTab = ref('basic')
const textLength = (value: unknown) => String(value || '').length

const tabs = [
    { key: 'basic', label: 'Basic', icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z' },
    { key: 'prompts', label: 'Prompts', icon: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z' },
    { key: 'fields', label: 'Fields', icon: 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z' },
    { key: 'content', label: 'Content', icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
    { key: 'seo', label: 'SEO', icon: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z' },
]

const form = useForm({
    // Basic
    name: props.template?.name || '',
    slug: props.template?.slug || '',
    description: props.template?.description || '',
    category_id: props.template?.category_id || '',
    icon: props.template?.icon || '',
    color: props.template?.color || '#6366f1',
    sort_order: props.template?.sort_order || 0,
    is_active: props.template?.is_active ?? true,
    is_featured: props.template?.is_featured ?? false,
    requires_pro: props.template?.requires_pro ?? false,
    access_level: props.template?.access_level || 'inherit',

    // Prompts
    prompt_system: props.template?.prompt_system || '',
    prompt_user: props.template?.prompt_user || '',
    output_type: props.template?.output_type || 'markdown',
    model_override: props.template?.model_override || '',
    max_tokens_override: props.template?.max_tokens_override || '',
    temperature: props.template?.temperature ?? 0.7,
    supports_brand_voice: props.template?.supports_brand_voice ?? true,
    avg_output_tokens: props.template?.avg_output_tokens || '',

    // Fields
    fields: props.template?.fields ? (typeof props.template.fields === 'string' ? JSON.parse(props.template.fields) : props.template.fields) : [],

    // Content
    about_content: props.template?.about_content || '',
    how_it_works: props.template?.how_it_works ? (typeof props.template.how_it_works === 'string' ? JSON.parse(props.template.how_it_works) : props.template.how_it_works) : [],
    usage_examples: props.template?.usage_examples ? (typeof props.template.usage_examples === 'string' ? JSON.parse(props.template.usage_examples) : props.template.usage_examples) : [],
    faq_items: props.template?.faq_items ? (typeof props.template.faq_items === 'string' ? JSON.parse(props.template.faq_items) : props.template.faq_items) : [],
    show_about: props.template?.show_about ?? true,
    show_how_it_works: props.template?.show_how_it_works ?? true,
    show_usage_examples: props.template?.show_usage_examples ?? true,
    show_faqs: props.template?.show_faqs ?? true,
    show_reviews: props.template?.show_reviews ?? true,
    show_related_tools: props.template?.show_related_tools ?? true,

    // SEO
    meta_title: props.template?.meta_title || '',
    meta_description: props.template?.meta_description || '',
    og_image: props.template?.og_image || '',
})

// Auto-slug from name
const autoSlug = () => {
    if (!isEditing.value) {
        form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
    }
}

// Field management
const fieldTypes = [
    'text', 'textarea', 'select', 'number', 'toggle', 'slider', 'color',
    'tags_input', 'tone_select', 'language_select', 'length_select',
    'model_select', 'image_upload', 'file_upload', 'code_input', 'url',
]

const addField = () => {
    form.fields.push({ name: '', key: '', label: '', type: 'text', required: false, placeholder: '', options: [] })
}
const removeField = (index: number) => {
    form.fields.splice(index, 1)
}

// FAQ management
const addFaq = () => {
    form.faq_items.push({ question: '', answer: '' })
}
const removeFaq = (index: number) => {
    form.faq_items.splice(index, 1)
}

const addStep = () => {
    form.how_it_works.push({
        step: form.how_it_works.length + 1,
        icon: 'ti-forms',
        title: '',
        description: '',
    })
}
const removeStep = (index: number) => {
    form.how_it_works.splice(index, 1)
    form.how_it_works.forEach((step: any, i: number) => {
        step.step = i + 1
    })
}
const addExample = () => {
    form.usage_examples.push({
        title: '',
        input: {},
        output: '',
    })
}
const removeExample = (index: number) => {
    form.usage_examples.splice(index, 1)
}
const updateExampleInput = (example: any, value: string) => {
    try {
        example.input = JSON.parse(value || '{}')
    } catch {
        example.input = {}
    }
}

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.ai.templates.update', props.template.id))
    } else {
        form.post(route('admin.ai.templates.store'))
    }
}
</script>

<template>
    <Head :title="(isEditing ? 'Edit' : 'Create') + ' Template — Admin'" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.ai.templates.index')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-800 text-gray-400 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isEditing ? 'Edit Template' : 'Create Template' }}</h1>
                </div>
                <p v-if="isEditing" class="text-sm text-gray-500 dark:text-gray-400 mt-1 ml-11">{{ template.slug }}</p>
            </div>
            <button @click="submit" :disabled="form.processing" :class="form.processing ? 'opacity-50 cursor-wait' : ''" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-lg shadow-primary-500/20 transition-all">
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                {{ isEditing ? 'Save Changes' : 'Create Template' }}
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
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Name *</label>
                            <input v-model="form.name" @input="autoSlug" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="$t('Blog Post Writer')" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Slug *</label>
                            <input v-model="form.slug" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Description *</label>
                        <textarea v-model="form.description" rows="2" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="$t('Generate professional blog posts...')" />
                    </div>
                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                            <select v-model="form.category_id" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm">
                                <option value="">None</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Icon</label>
                            <input v-model="form.icon" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" placeholder="pencil" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.color" type="color" class="w-10 h-10 rounded-lg border-0 cursor-pointer" />
                                <input v-model="form.color" type="text" class="flex-1 px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Sort Order</label>
                            <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Access Level</label>
                            <select v-model="form.access_level" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm">
                                <option value="inherit">Inherit</option>
                                <option value="public">Public</option>
                                <option value="login_required">Login Required</option>
                                <option value="free_plan">Free Plan</option>
                                <option value="pro_plan">Pro Plan</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_featured" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Featured</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.requires_pro" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">Requires Pro</span>
                        </label>
                    </div>
                </div>

                <!-- ═══ TAB: Prompts ═══ -->
                <div v-show="activeTab === 'prompts'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">System Prompt</label>
                        <textarea v-model="form.prompt_system" rows="5" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" :placeholder="$t('You are a professional writer...')" />
                        <p class="text-xs text-gray-400 mt-1">Instructions for the AI's behavior and persona.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">User Prompt Template</label>
                        <textarea v-model="form.prompt_user" rows="5" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" :placeholder="$t('Write a blog post about {topic}...')" />
                        <p class="text-xs text-gray-400 mt-1">Use <code class="text-primary-500">{field_key}</code> for dynamic field placeholders.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Output Type</label>
                            <select v-model="form.output_type" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm">
                                <option value="text">Text</option>
                                <option value="markdown">Markdown</option>
                                <option value="html">HTML</option>
                                <option value="code">Code</option>
                                <option value="list">List</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Temperature</label>
                            <input v-model.number="form.temperature" type="number" step="0.1" min="0" max="2" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Model Override</label>
                            <input v-model="form.model_override" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" :placeholder="$t('Leave empty for default')" />
                        </div>
                    </div>
                </div>

                <!-- ═══ TAB: Fields ═══ -->
                <div v-show="activeTab === 'fields'" class="space-y-4">
                    <div v-for="(field, i) in form.fields" :key="i" class="p-4 bg-gray-50 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-gray-400 uppercase">Field {{ Number(i) + 1 }}</span>
                            <button @click="removeField(Number(i))" class="p-1 text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 gap-3">
                            <input v-model="field.name" @input="field.key = field.name" type="text" placeholder="name" class="px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm font-mono" />
                            <input v-model="field.label" type="text" :placeholder="$t('Label')" class="px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm" />
                            <select v-model="field.type" class="px-3 py-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg text-sm">
                                <option v-for="type in fieldTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <label class="flex items-center gap-2">
                                <input v-model="field.required" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" />
                                <span class="text-sm text-gray-600 dark:text-gray-400">Required</span>
                            </label>
                        </div>
                    </div>
                    <button @click="addField" class="w-full py-3 border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-xl text-sm font-semibold text-gray-400 hover:text-primary-500 hover:border-primary-300 transition-colors">
                        + Add Field
                    </button>
                </div>

                <!-- ═══ TAB: Content ═══ -->
                <div v-show="activeTab === 'content'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">About Content</label>
                        <textarea v-model="form.about_content" rows="4" class="w-full px-4 py-3 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="$t('Describe what this tool does...')" />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">How It Works</label>
                        <div v-for="(step, i) in form.how_it_works" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">Step {{ Number(i) + 1 }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="removeStep(Number(i))">Remove</button>
                            </div>
                            <div class="grid gap-3 md:grid-cols-[80px_1fr_1fr]">
                                <input v-model.number="step.step" type="number" min="1" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                                <input v-model="step.icon" type="text" placeholder="ti-forms" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-900" />
                                <input v-model="step.title" type="text" :placeholder="$t('Step title')" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                            </div>
                            <textarea v-model="step.description" rows="2" :placeholder="$t('Short step description')" class="mt-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addStep">+ Add Step</button>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Usage Examples</label>
                        <div v-for="(example, i) in form.usage_examples" :key="i" class="mb-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase text-gray-400">Example {{ Number(i) + 1 }}</span>
                                <button type="button" class="text-xs font-semibold text-red-500 hover:text-red-600" @click="removeExample(Number(i))">Remove</button>
                            </div>
                            <input v-model="example.title" type="text" :placeholder="$t('Example title')" class="mb-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                            <textarea
                                :value="JSON.stringify(example.input || {}, null, 2)"
                                rows="4"
                                class="mb-3 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 font-mono text-xs dark:border-surface-700 dark:bg-surface-900"
                                placeholder='{"topic":"Laravel","tone":"Professional"}'
                                @input="updateExampleInput(example, ($event.target as HTMLTextAreaElement).value)"
                            />
                            <textarea v-model="example.output" rows="4" :placeholder="$t('Generated output preview')" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900" />
                        </div>
                        <button type="button" class="text-sm font-semibold text-primary-600 hover:text-primary-700" @click="addExample">+ Add Example</button>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">FAQs</label>
                        <div v-for="(faq, i) in form.faq_items" :key="i" class="flex gap-3 mb-3">
                            <div class="flex-1 space-y-2">
                                <input v-model="faq.question" type="text" :placeholder="$t('Question')" class="w-full px-3 py-2 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg text-sm" />
                                <textarea v-model="faq.answer" rows="2" :placeholder="$t('Answer')" class="w-full px-3 py-2 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg text-sm" />
                            </div>
                            <button @click="removeFaq(Number(i))" class="p-1 text-gray-400 hover:text-red-500 self-start mt-2"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                        </div>
                        <button @click="addFaq" class="text-sm font-semibold text-primary-600 hover:text-primary-700">+ Add FAQ</button>
                    </div>
                    <div class="flex flex-wrap gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_about" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show About</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_how_it_works" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show How It Works</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_usage_examples" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show Examples</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_faqs" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show FAQs</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_reviews" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show Reviews</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input v-model="form.show_related_tools" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600" /><span class="text-sm text-gray-700 dark:text-gray-300">Show Related</span></label>
                    </div>
                </div>

                <!-- ═══ TAB: SEO ═══ -->
                <div v-show="activeTab === 'seo'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Meta Title</label>
                        <input v-model="form.meta_title" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="$t('Leave empty for auto-generated')" />
                        <p class="text-xs text-gray-400 mt-1">{{ textLength(form.meta_title) }} / 60 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Meta Description</label>
                        <textarea v-model="form.meta_description" rows="2" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" :placeholder="$t('Leave empty for auto-generated')" />
                        <p class="text-xs text-gray-400 mt-1">{{ textLength(form.meta_description) }} / 155 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">OG Image URL</label>
                        <input v-model="form.og_image" type="text" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" placeholder="https://..." />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
