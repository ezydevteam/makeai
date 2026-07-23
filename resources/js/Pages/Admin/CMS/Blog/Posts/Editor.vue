<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onClickOutside } from '@vueuse/core'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import TagsInput from '@/Components/UI/TagsInput.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import { mediaUrl } from '@/lib/media'
import { fromDateTimeLocalInput, toDateTimeLocalInput } from '@/lib/datetime'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

interface Category { id: number; name: string; color?: string | null }
interface Author { id: number; name: string }
interface ExistingTag { name: string }
interface Revision { id: number; title: string; created_at: string; admin?: { name: string } | null }
interface BlogPost {
    ulid: string
    author_id: number
    title: string
    slug: string
    excerpt: string | null
    content: string
    featured_image: string | null
    featured_image_alt: string | null
    status: 'draft' | 'published' | 'scheduled' | 'private'
    published_at: string | null
    scheduled_at: string | null
    is_featured: boolean
    is_sticky: boolean
    allow_comments: boolean
    reading_time: number
    meta_title: string | null
    meta_description: string | null
    meta_keywords: string | null
    og_title: string | null
    og_description: string | null
    og_image: string | null
    canonical_url: string | null
    schema_type: 'Article' | 'BlogPosting' | 'NewsArticle'
    no_index: boolean
    show_related_posts: boolean
    show_toc: boolean
    categories: Category[]
    tags: ExistingTag[]
    revisions?: Revision[]
}

interface RichEditorExpose {
    getSelectedText: () => string
    replaceSelection: (html: string) => void
    insertAtCursor: (html: string) => void
}

interface AiAssistAction {
    key: string
    label: string
    description: string
}

interface SelectOption {
    value: string | number
    label: string
}

const props = defineProps<{
    post: BlogPost | null
    categories: Category[]
    tags: ExistingTag[]
    authors: Author[]
    defaults: { author_id: number; allow_comments: boolean; show_reading_time: boolean }
}>()

const { t } = useTranslate()
const toast = useToastr()
const currentPostUlid = ref(props.post?.ulid ?? '')
const isEditing = computed(() => Boolean(currentPostUlid.value))
const slugTouched = ref(Boolean(props.post?.slug))
const editorRef = ref<RichEditorExpose | null>(null)
const isDirty = ref(false)
const isAutosaving = ref(false)
const lastAutosavedAt = ref<string | null>(null)
const autosaveError = ref<string | null>(null)
const aiLoading = ref<string | null>(null)
const aiError = ref(false)
let autosaveTimer: number | undefined
let suppressDirty = false


const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    content: props.post?.content ?? '',
    featured_image: props.post?.featured_image ?? '',
    featured_image_file: null as File | null,
    featured_image_alt: props.post?.featured_image_alt ?? '',
    status: props.post?.status ?? 'draft',
    published_at: toDateTimeLocalInput(props.post?.published_at),
    scheduled_at: toDateTimeLocalInput(props.post?.scheduled_at),
    author_id: props.post?.author_id ?? props.defaults.author_id,
    is_featured: props.post?.is_featured ?? false,
    is_sticky: props.post?.is_sticky ?? false,
    allow_comments: props.post?.allow_comments ?? props.defaults.allow_comments,
    reading_time: props.post?.reading_time ?? 0,
    meta_title: props.post?.meta_title ?? '',
    meta_description: props.post?.meta_description ?? '',
    meta_keywords: props.post?.meta_keywords ?? '',
    og_title: props.post?.og_title ?? '',
    og_description: props.post?.og_description ?? '',
    og_image: props.post?.og_image ?? '',
    canonical_url: props.post?.canonical_url ?? '',
    schema_type: props.post?.schema_type ?? 'BlogPosting',
    no_index: props.post?.no_index ?? false,
    show_related_posts: props.post?.show_related_posts ?? true,
    show_toc: props.post?.show_toc ?? false,
    category_ids: props.post?.categories?.map((category) => category.id) ?? [],
    tags: props.post?.tags?.map((tag) => tag.name).join(', ') ?? '',
})

const toggles = [
    { key: 'is_featured', label: 'Make Featured' },
    { key: 'is_sticky', label: 'Make Pinned' },
    { key: 'show_toc', label: 'Show table of contents' },
    { key: 'no_index', label: 'Hide from search engine' },
] as const

const blogAiAssistActions: AiAssistAction[] = [
    {
        key: 'generate_title',
        label: t('Generate title'),
        description: t('Create a title from the current post content.'),
    },
    {
        key: 'continue_writing',
        label: t('Generate content'),
        description: t('Continue writing from the cursor.'),
    },
    {
        key: 'generate_excerpt',
        label: t('Generate excerpt'),
        description: t('Create a short blog summary.'),
    },
    {
        key: 'generate_seo',
        label: t('Generate SEO'),
        description: t('Fill meta title and description.'),
    },
    {
        key: 'generate_tags',
        label: t('Generate tags'),
        description: t('Suggest searchable blog tags.'),
    },
    {
        key: 'improve_paragraph',
        label: t('Improve selection'),
        description: t('Rewrite selected text for clarity.'),
    },
]

const selectionAiActions = [
    'improve_paragraph',
    'improve_selection',
    'shorten_selection',
    'expand_selection',
    'rephrase_selection',
    'translate_selection',
    'change_tone',
    'summarize_selection',
    'fix_grammar',
]

const authorOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('Select author') },
    ...props.authors.map((author) => ({
        value: String(author.id),
        label: author.name,
    })),
])

const categoryOptions = computed<SelectOption[]>(() => props.categories.map((category) => ({
    value: category.id,
    label: category.name,
})))

const schemaTypeOptions = computed<SelectOption[]>(() => [
    { value: 'BlogPosting', label: 'Blog Posting' },
    { value: 'Article', label: 'Article' },
    { value: 'NewsArticle', label: 'News Article' },
])

const publishMenuOpen = ref(false)
const publishMenuRef = ref<HTMLElement | null>(null)

const primaryActionLabel = computed(() => {
    if (form.status === 'published') {
        return t('Publish')
    }

    if (form.status === 'draft') {
        return t('Save Draft')
    }

    if (form.status === 'scheduled') {
        return t('Schedule')
    }

    return t('Save Private')
})

const setStatus = (value: BlogPost['status']) => {
    form.status = value
    publishMenuOpen.value = false
}

onClickOutside(publishMenuRef, () => {
    publishMenuOpen.value = false
})

const makeSlug = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

const syncSlug = () => {
    if (slugTouched.value) return
    form.slug = makeSlug(form.title)
}

const markSlugTouched = () => {
    slugTouched.value = true
    form.slug = makeSlug(form.slug)
}

const featuredPreview = ref<string | null>(props.post?.featured_image ? mediaUrl(props.post.featured_image) : null)

const setFeaturedImage = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null
    form.featured_image_file = file
    if (file) {
        featuredPreview.value = URL.createObjectURL(file)
    }
}

const removeFeaturedImage = () => {
    form.featured_image = ''
    form.featured_image_file = null
    featuredPreview.value = null
}

const normalizedTags = () => form.tags.split(',').map((tag) => tag.trim()).filter(Boolean)

const editorPayload = () => ({
    title: form.title,
    slug: form.slug,
    excerpt: form.excerpt,
    content: form.content,
    featured_image: form.featured_image,
    featured_image_alt: form.featured_image_alt,
    status: form.status,
    published_at: fromDateTimeLocalInput(form.published_at),
    scheduled_at: fromDateTimeLocalInput(form.scheduled_at),
    author_id: form.author_id,
    is_featured: form.is_featured,
    is_sticky: form.is_sticky,
    allow_comments: form.allow_comments,
    reading_time: form.reading_time,
    meta_title: form.meta_title,
    meta_description: form.meta_description,
    meta_keywords: form.meta_keywords,
    og_title: form.og_title,
    og_description: form.og_description,
    og_image: form.og_image,
    canonical_url: form.canonical_url,
    schema_type: form.schema_type,
    no_index: form.no_index,
    show_related_posts: form.show_related_posts,
    show_toc: form.show_toc,
    category_ids: form.category_ids,
    tags: normalizedTags(),
})

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

const autoSave = async () => {
    if (!isDirty.value || isAutosaving.value || form.featured_image_file) return

    isAutosaving.value = true
    autosaveError.value = null

    const endpoint = currentPostUlid.value
        ? route('admin.blog.posts.autosave.update', currentPostUlid.value)
        : route('admin.blog.posts.autosave')

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(editorPayload()),
        })
        const payload = await response.json()

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || t('Auto-save failed.'))
        }

        if (!currentPostUlid.value) {
            currentPostUlid.value = payload.data.ulid
            window.history.replaceState({}, '', payload.data.edit_url)
        }

        if (!slugTouched.value && payload.data.slug) {
            suppressDirty = true
            form.slug = payload.data.slug
            window.setTimeout(() => { suppressDirty = false }, 0)
        }

        lastAutosavedAt.value = payload.data.saved_at
        isDirty.value = false
    } catch (error) {
        autosaveError.value = error instanceof Error ? error.message : t('Auto-save failed.')
    } finally {
        isAutosaving.value = false
    }
}

const runAiAssist = async (action: string) => {
    if (aiLoading.value) return

    aiLoading.value = action
    aiError.value = false
    const selectedText = editorRef.value?.getSelectedText() ?? ''

    try {
        const response = await fetch(route('admin.blog.posts.ai-assist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                action,
                title: form.title,
                content: form.content,
                selected_text: selectedText,
            }),
        })
        const payload = await response.json()

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || t('AI assist failed.'))
        }

        if (action === 'generate_title') {
            form.title = payload.data.content
            syncSlug()
        } else if (action === 'generate_excerpt') {
            form.excerpt = payload.data.content
        } else if (action === 'generate_seo') {
            form.meta_title = payload.data.meta_title
            form.meta_description = payload.data.meta_description
        } else if (action === 'generate_tags') {
            form.tags = payload.data.tags.join(', ')
        } else if (selectionAiActions.includes(action)) {
            editorRef.value?.replaceSelection(payload.data.content)
        } else if (action === 'continue_writing') {
            editorRef.value?.insertAtCursor(payload.data.content)
        }

        isDirty.value = true
        toast.success(t('AI assist applied.'))
    } catch (error) {
        aiError.value = true
        toast.error(error instanceof Error ? error.message : t('AI assist failed.'))
    } finally {
        aiLoading.value = null
    }
}

const submit = () => {
    const payload = {
        ...form.data(),
        tags: normalizedTags(),
        _method: currentPostUlid.value ? 'put' : undefined,
    }

    if (currentPostUlid.value) {
        form.transform(() => payload).post(route('admin.blog.posts.update', currentPostUlid.value), {
            forceFormData: true,
            onSuccess: () => { isDirty.value = false },
        })
    } else {
        form.transform(() => payload).post(route('admin.blog.posts.store'), {
            forceFormData: true,
            onSuccess: () => { isDirty.value = false },
        })
    }
}

watch(
    () => JSON.stringify(editorPayload()),
    () => {
        if (suppressDirty) return
        isDirty.value = true
        autosaveError.value = null
    }
)

onMounted(() => {
    autosaveTimer = window.setInterval(autoSave, 60000)
})

onBeforeUnmount(() => {
    if (autosaveTimer) window.clearInterval(autosaveTimer)
})
</script>

<template>
    <Head :title="isEditing ? t('Edit Blog Post') : t('Create Blog Post')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ isEditing ? t('Edit Blog Post') : t('Create Blog Post') }}</h1>
                    <span v-if="isAutosaving" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">{{ t('Auto-saving...') }}</span>
                    <span v-else-if="autosaveError" class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-200">{{ autosaveError }}</span>
                    <span v-else-if="isDirty" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">{{ t('Unsaved changes') }}</span>
                    <span v-else-if="lastAutosavedAt" class="rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ t('Saved') }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ t('Write content and configure SEO, layout, and publishing details.') }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <Link :href="route('admin.blog.posts.index')" class="inline-flex items-center justify-center gap-2 rounded-xl transition px-3 py-2 border border-gray-200 hover:border-gray-200 hover:bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-gray-800 dark:hover:bg-gray-900/20 dark:hover:text-gray-300">
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <div ref="publishMenuRef" class="relative">
                    <div class="inline-flex overflow-hidden rounded-xl shadow-sm ring-1 ring-primary-600/20">
                        <button @click="submit" :disabled="form.processing" type="button" class="bg-primary-500 inline-flex items-center justify-center gap-2 !rounded-l-xl !rounded-r-none px-3 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ form.processing ? t('Saving...') : primaryActionLabel }}
                        </button>
                        <button
                            type="button"
                            class="bg-primary-500 inline-flex items-center justify-center !rounded-r-xl !rounded-l-none px-2 text-white disabled:opacity-60"
                            :aria-label="t('Change post status')"
                            @click="publishMenuOpen = !publishMenuOpen"
                        >
                            <i class="ti ti-chevron-down text-base"></i>
                        </button>
                    </div>

                    <div v-if="publishMenuOpen" class="absolute right-0 top-full z-20 mt-2 w-52 rounded-xl border border-gray-200 bg-white py-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('draft')">
                            <span class="inline-flex items-center gap-2">
                                <i class="ti ti-notebook text-base text-gray-400 dark:text-gray-500"></i>
                                {{ t('Save as Draft') }}
                            </span>
                            <i v-if="form.status === 'draft'" class="ti ti-check text-base text-primary-600"></i>
                        </button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('scheduled')">
                            <span class="inline-flex items-center gap-2">
                                <i class="ti ti-calendar-time text-base text-gray-400 dark:text-gray-500"></i>
                                {{ t('Schedule Post') }}
                            </span>
                            <i v-if="form.status === 'scheduled'" class="ti ti-check text-base text-primary-600"></i>
                        </button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('private')">
                            <span class="inline-flex items-center gap-2">
                                <i class="ti ti-lock-star text-base text-gray-400 dark:text-gray-500"></i>
                                {{ t('Save as Private') }}
                            </span>
                            <i v-if="form.status === 'private'" class="ti ti-check text-base text-primary-600"></i>
                        </button>
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('published')">
                            <span class="inline-flex items-center gap-2">
                                <i class="ti ti-rocket text-base text-gray-400 dark:text-gray-500"></i>
                                {{ t('Publish Now') }}
                            </span>
                            <i v-if="form.status === 'published'" class="ti ti-check text-base text-primary-600"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-6">
            <main class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</label>
                    <input v-model="form.title" @input="syncSlug" :placeholder="t('Enter blog post title')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-lg font-semibold dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <p v-if="form.errors.title" class="mt-1 text-sm text-danger-600">{{ form.errors.title }}</p>

                    <div class="mt-5">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.slug" @input="markSlugTouched" :placeholder="t('blog-post-slug')" type="text" class="w-full flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                            <Tooltip :content="currentPostUlid ? t('Preview post') : t('Save the post to enable preview')" placement="top">
                                <a
                                    :href="currentPostUlid ? route('admin.blog.posts.preview', currentPostUlid) : undefined"
                                    :target="currentPostUlid ? '_blank' : undefined"
                                    rel="noopener"
                                    :aria-disabled="!currentPostUlid"
                                    :aria-label="t('Preview post')"
                                    :class="[
                                        'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition',
                                        currentPostUlid
                                            ? 'cursor-pointer border-gray-200 bg-gray-50 text-gray-500 hover:border-primary-500 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400 dark:hover:text-primary-300'
                                            : 'pointer-events-none cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300 dark:border-surface-800 dark:bg-surface-800 dark:text-surface-600',
                                    ]"
                                >
                                    <i class="ti ti-eye text-base"></i>
                                </a>
                            </Tooltip>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">{{ t('Generated from title. You can edit it.') }}</p>
                    </div>

                    <label class="mt-5 mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }}</label>
                    <RichEditor
                        ref="editorRef"
                        v-model="form.content"
                        variant="full"
                        ai-assist
                        :ai-assist-actions="blogAiAssistActions"
                        :ai-assist-loading-key="aiLoading"
                        :ai-assist-error="aiError"
                        :ai-assist-label="t('AI Assist')"
                        :ai-assist-loading-label="t('Working...')"
                        @ai-assist="runAiAssist"
                    />
                    <p v-if="form.errors.content" class="mt-1 text-sm text-danger-600">{{ form.errors.content }}</p>

                    <label class="mt-5 mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Excerpt') }}</label>
                    <textarea v-model="form.excerpt" :placeholder="t('Write a short excerpt for listing pages and sharing')" rows="4" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                    <p v-if="form.errors.excerpt" class="mt-1 text-sm text-danger-600">{{ form.errors.excerpt }}</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('SEO') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="mb-2 flex justify-between text-sm"><label class="font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Title') }}</label><span class="text-gray-400">{{ form.meta_title.length }}/60</span></div>
                            <input v-model="form.meta_title" maxlength="255" :placeholder="t('Write an SEO title')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div>
                            <AppSelect v-model="form.schema_type" :options="schemaTypeOptions" :label="t('Schema Type')" />
                        </div>
                        <div class="md:col-span-2">
                            <div class="mb-2 flex justify-between text-sm"><label class="font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Description') }}</label><span class="text-gray-400">{{ form.meta_description.length }}/160</span></div>
                            <textarea v-model="form.meta_description" :placeholder="t('Write a concise meta description')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Keywords') }}</label>
                            <input v-model="form.meta_keywords" :placeholder="t('Add comma separated meta keywords')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Canonical URL') }}</label>
                            <input v-model="form.canonical_url" :placeholder="t('https://example.com/blog/post')" type="url" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Open Graph Title') }}</label>
                            <input v-model="form.og_title" :placeholder="t('Write an Open Graph title')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Open Graph Image URL') }}</label>
                            <input v-model="form.og_image" :placeholder="t('Paste an Open Graph image URL')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Open Graph Description') }}</label>
                            <textarea v-model="form.og_description" :placeholder="t('Write an Open Graph description')" rows="2" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                        </div>
                    </div>
                </section>

            </main>

            <aside class="space-y-5">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Publishing') }}</h2>
                    <div class="space-y-4">
                        <div v-if="form.status === 'scheduled'">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Scheduled At') }}</label>
                            <input v-model="form.scheduled_at" type="datetime-local" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        </div>
                        <div>
                            <AppSelect v-model="form.author_id" :options="authorOptions" :label="t('Author')" :placeholder="t('Select author')" />
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Categories & Tags') }}</h2>
                    <AppSelect
                        v-if="categories.length"
                        v-model="form.category_ids"
                        :options="categoryOptions"
                        :label="t('Categories')"
                        :placeholder="t('Select categories')"
                        multiple
                        compact-multiple
                        live-search
                    />
                    <div v-else class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-surface-700">
                        <p>{{ t('No active blog categories yet.') }}</p>
                        <Link :href="route('admin.blog.categories.index')" class="mt-2 inline-flex text-primary-600 hover:text-primary-500">{{ t('Create category') }}</Link>
                    </div>
                    <div class="mt-4">
                        <TagsInput v-model="form.tags" :label="t('Tags')" :placeholder="t('Type a tag and press Enter')" :suggestions="tags.map((tag) => tag.name)" />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Featured Image') }}</h2>
                    <div class="relative group">
                        <div class="aspect-video overflow-hidden rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center dark:border-surface-700 dark:bg-surface-800">
                            <img v-if="featuredPreview" :src="featuredPreview" :alt="form.featured_image_alt || form.title" class="w-full h-full object-cover">
                            <svg v-else class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <label class="absolute inset-0 flex items-center justify-center bg-gray-900/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg cursor-pointer">
                            <input @change="setFeaturedImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
                            <span class="text-white text-sm font-semibold">{{ t('Update Image') }}</span>
                        </label>
                    </div>
                    <button v-if="featuredPreview" @click="removeFeaturedImage" type="button" class="mt-3 flex items-center gap-1 text-xs font-semibold text-red-500 transition-colors hover:text-red-700">
                        <i class="ti ti-trash text-sm"></i>
                        {{ t('Remove Featured Image') }}
                    </button>
                    <p v-if="form.errors.featured_image_file" class="mt-1 text-sm text-danger-600">{{ form.errors.featured_image_file }}</p>
                    <label class="mt-4 mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Image Alt Text') }}</label>
                    <input v-model="form.featured_image_alt" :placeholder="t('Describe the image for accessibility')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <label class="mt-4 mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reading Time') }}</label>
                    <input v-model="form.reading_time" :placeholder="t('Estimated reading time in minutes')" type="number" min="0" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Layout Options') }}</h2>
                    <div class="space-y-4">
                        <div v-for="field in toggles" :key="field.key" class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t(field.label) }}</p>
                            <AppSwitch
                                :model-value="Boolean(form[field.key])"
                                @update:model-value="val => form[field.key] = val"
                            />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Disable comments') }}</p>
                            <AppSwitch
                                :model-value="!form.allow_comments"
                                @update:model-value="val => form.allow_comments = !val"
                            />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ t('Hide related posts') }}</p>
                            <AppSwitch
                                :model-value="!form.show_related_posts"
                                @update:model-value="val => form.show_related_posts = !val"
                            />
                        </div>
                    </div>
                </section>

                <section v-if="post?.revisions?.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                    <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Revisions') }}</h2>
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <div v-for="revision in post.revisions.slice(0, 6)" :key="revision.id" class="rounded-lg bg-gray-50 p-3 dark:bg-surface-800">
                            <div class="font-medium">{{ revision.title }}</div>
                            <div class="text-xs text-gray-400">{{ revision.admin?.name }} · {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(revision.created_at)) }}</div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
