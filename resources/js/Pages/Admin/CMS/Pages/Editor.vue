<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core'
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useToastr } from '@/Composables/useToastr';
import { useTranslate } from '@/Composables/useTranslate';
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue';

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))
import AppSelect from '@/Components/AppSelect.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    page: any;
    parents: any[];
}>();

interface RichEditorExpose {
    getSelectedText: () => string;
    replaceSelection: (html: string) => void;
    insertAtCursor: (html: string) => void;
}

interface AiAssistAction {
    key: string;
    label: string;
    description: string;
}

interface ConfirmModalState {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    processingLabel: string;
    processing: boolean;
    variant: 'primary' | 'danger';
    action: null | (() => void);
}

const { t } = useTranslate();
const toast = useToastr();
const editorRef = ref<RichEditorExpose | null>(null);
const slugTouched = ref(Boolean(props.page?.slug));
const aiLoading = ref<string | null>(null);
const confirmModal = ref<ConfirmModalState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    variant: 'primary',
    action: null,
});

const showPassword = ref(false);

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    content: props.page?.content ?? '',
    excerpt: props.page?.excerpt ?? '',
    meta_title: props.page?.meta_title ?? '',
    meta_description: props.page?.meta_description ?? '',
    meta_keywords: props.page?.meta_keywords ?? '',
    template: props.page?.template ?? 'default',
    status: props.page?.status ?? 'published',
    published_at: props.page?.published_at ? new Date(props.page.published_at).toISOString().slice(0, 16) : null,
    password: '',
    parent_id: props.page?.parent_id ?? null,
    sort_order: props.page?.sort_order ?? 0,
    show_title: props.page?.show_title ?? true,
    center_title: props.page?.center_title ?? false,
    show_breadcrumbs: props.page?.show_breadcrumbs ?? true,
    show_featured_image: props.page?.show_featured_image ?? true,
    show_sidebar: props.page?.show_sidebar ?? false,
    sidebar_position: props.page?.sidebar_position ?? 'right',
    container_width: props.page?.container_width ?? '1280px',
    featured_image: null as File | null,
    og_image: null as File | null,
    remove_featured_image: false,
    remove_og_image: false,
});

const featuredPreview = ref(props.page?.featured_image ? `/storage/${props.page.featured_image}` : null);
const ogPreview = ref(props.page?.og_image ? `/storage/${props.page.og_image}` : null);
const publishMenuOpen = ref(false)
const publishMenuRef = ref<HTMLElement | null>(null)

const statusOptions = computed(() => [
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
    { value: 'scheduled', label: t('Scheduled') },
]);

const primaryActionLabel = computed(() => {
    if (form.status === 'published') {
        return t('Publish')
    }

    if (form.status === 'scheduled') {
        return t('Schedule')
    }

    return t('Save Draft')
})

const parentOptions = computed(() => [
    { value: null, label: t('None (Top Level)') },
    ...props.parents.map((parent) => ({
        value: parent.id,
        label: parent.title,
    })),
]);

const containerWidthOptions = computed(() => [
    { value: '1280px', label: t('Default') },
    { value: 'full', label: t('Full Width') },
    { value: '1080px', label: t('Boxed') },
    { value: '1536px', label: t('Stretched') },
]);

const sidebarPositionOptions = computed(() => [
    { value: 'left', label: t('Sidebar Left') },
    { value: 'right', label: t('Sidebar Right') },
]);

const setStatus = (value: 'draft' | 'published' | 'scheduled') => {
    form.status = value
    publishMenuOpen.value = false
}

const pageAiAssistActions: AiAssistAction[] = [
    {
        key: 'generate_title',
        label: t('Generate title'),
        description: t('Create a page title from the current content.'),
    },
    {
        key: 'generate_content',
        label: t('Generate content'),
        description: t('Write or continue page body content.'),
    },
    {
        key: 'generate_excerpt',
        label: t('Generate excerpt'),
        description: t('Create a short page summary.'),
    },
    {
        key: 'generate_seo',
        label: t('Generate SEO'),
        description: t('Fill meta title, description, and keywords.'),
    },
    {
        key: 'improve_selection',
        label: t('Improve selection'),
        description: t('Rewrite selected text for clarity.'),
    },
];

const selectionAiActions = [
    'improve_selection',
    'shorten_selection',
    'expand_selection',
    'rephrase_selection',
    'translate_selection',
    'change_tone',
    'summarize_selection',
    'fix_grammar',
];

const makeSlug = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

const syncSlug = () => {
    if (slugTouched.value) return;
    form.slug = makeSlug(form.title);
};

const markSlugTouched = () => {
    slugTouched.value = true;
    form.slug = makeSlug(form.slug);
};

const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

const runAiAssist = async (action: string) => {
    if (aiLoading.value) return;

    aiLoading.value = action;
    const selectedText = editorRef.value?.getSelectedText() ?? '';

    try {
        const response = await fetch(route('admin.pages.ai-assist'), {
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
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || t('AI assist failed.'));
        }

        if (action === 'generate_title') {
            form.title = payload.data.content;
            syncSlug();
        } else if (action === 'generate_content') {
            editorRef.value?.insertAtCursor(payload.data.content);
        } else if (action === 'generate_excerpt') {
            form.excerpt = payload.data.content;
        } else if (action === 'generate_seo') {
            form.meta_title = payload.data.meta_title;
            form.meta_description = payload.data.meta_description;
            form.meta_keywords = payload.data.meta_keywords;
        } else if (selectionAiActions.includes(action)) {
            editorRef.value?.replaceSelection(payload.data.content);
        } else if (action === 'continue_writing') {
            editorRef.value?.insertAtCursor(payload.data.content);
        }

        toast.success(t('AI assist applied.'));
    } catch (error) {
        toast.error(error instanceof Error ? error.message : t('AI assist failed.'));
    } finally {
        aiLoading.value = null;
    }
};

const handleFeaturedChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.featured_image = file;
        featuredPreview.value = URL.createObjectURL(file);
        form.remove_featured_image = false;
    }
};

const handleOgChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.og_image = file;
        ogPreview.value = URL.createObjectURL(file);
        form.remove_og_image = false;
    }
};

const removeFeaturedImage = () => {
    form.featured_image = null;
    featuredPreview.value = null;
    form.remove_featured_image = true;
};

const removeOgImage = () => {
    form.og_image = null;
    ogPreview.value = null;
    form.remove_og_image = true;
};

const submit = () => {
    if (props.page) {
        form.post(route('admin.pages.update', props.page.id), {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.pages.store'), {
            forceFormData: true,
        });
    }
};

const openConfirmModal = (config: Omit<ConfirmModalState, 'open' | 'processing'>) => {
    confirmModal.value = {
        ...config,
        open: true,
        processing: false,
    };
};

const closeConfirmModal = (force = false) => {
    if (confirmModal.value.processing && !force) {
        return;
    }

    confirmModal.value = {
        open: false,
        title: '',
        message: '',
        confirmLabel: '',
        processingLabel: '',
        processing: false,
        variant: 'primary',
        action: null,
    };
};

const runConfirmedAction = () => {
    confirmModal.value.processing = true;
    confirmModal.value.action?.();
};

const confirmDeletePage = () => {
    if (!props.page || props.page.is_system) {
        return;
    }

    openConfirmModal({
        title: t('Move page to trash?'),
        message: t('Move page :name to trash?', { name: props.page.title }),
        confirmLabel: t('Move to Trash'),
        processingLabel: t('Moving...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.pages.delete', props.page.id), {
                preserveScroll: true,
                onSuccess: () => {
                    closeConfirmModal(true);
                },
                onFinish: () => {
                    closeConfirmModal(true);
                },
            });
        },
    });
};

onClickOutside(publishMenuRef, () => {
    publishMenuOpen.value = false
})
</script>

<template>
    <Head :title="page ? t('Edit Page') : t('Create Page')" />
    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ page ? t('Edit Page') : t('Create Page') }}</h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ t('Design, organize, and publish custom content for your public site from one workspace.') }}</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <button
                        v-if="page && !page.is_system"
                        @click="confirmDeletePage"
                        type="button"
                        class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30"
                    >
                        {{ t('Delete Page') }}
                    </button>

                    <Link
                        :href="route('admin.pages.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700  transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    >
                        <i class="ti ti-arrow-left text-base"></i>
                        {{ t('Back') }}
                    </Link>

                    <div ref="publishMenuRef" class="relative">
                        <div class="inline-flex overflow-hidden rounded-lg shadow-sm ring-1 ring-primary-600/20">
                            <button
                                @click="submit"
                                :disabled="form.processing"
                                type="button"
                                class="bg-primary-500 inline-flex items-center justify-center gap-2 !rounded-l-lg !rounded-r-none px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                            >
                                <i class="ti ti-device-floppy text-base"></i>
                                {{ form.processing ? t('Saving...') : primaryActionLabel }}
                            </button>
                            <button
                                type="button"
                                class="bg-primary-500 inline-flex items-center justify-center !rounded-r-lg !rounded-l-none px-2 text-white disabled:opacity-60"
                                :aria-label="t('Change page status')"
                                @click="publishMenuOpen = !publishMenuOpen"
                            >
                                <i class="ti ti-chevron-down text-base"></i>
                            </button>
                        </div>

                        <div v-if="publishMenuOpen" class="absolute right-0 top-full z-20 mt-2 w-52 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-surface-700 dark:bg-surface-900">
                            <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('draft')">
                                <span class="inline-flex items-center gap-2">
                                    <i class="ti ti-notebook text-base text-gray-400 dark:text-gray-500"></i>
                                    {{ t('Save as Draft') }}
                                </span>
                                <i v-if="form.status === 'draft'" class="ti ti-check text-base text-primary-600"></i>
                            </button>
                            <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('scheduled')">
                                <span class="inline-flex items-center gap-2">
                                    <i class="ti ti-calendar-time text-base text-gray-400 dark:text-gray-500"></i>
                                    {{ t('Schedule Page') }}
                                </span>
                                <i v-if="form.status === 'scheduled'" class="ti ti-check text-base text-primary-600"></i>
                            </button>
                            <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-surface-800" @click="setStatus('published')">
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
        </div>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Editor Column -->
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="space-y-6">
                    <div>
                        <label class="mb-3 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Page Title') }}</label>
                        <input v-model="form.title" @input="syncSlug" type="text" :placeholder="t('Enter page title')" class="w-full border-none bg-transparent p-0 text-4xl font-black text-gray-900 placeholder:text-gray-300 focus:ring-0 dark:text-white dark:placeholder:text-gray-600">
                        <p v-if="form.errors.title" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.title }}</p>
                        <div class="mt-4 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-800">
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Page Slug') }}</label>
                            <div class="flex flex-col gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 md:flex-row md:items-center">
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $page.props.app?.url }}/</span>
                                <input v-model="form.slug" @input="markSlugTouched" type="text" :placeholder="t('page-slug')" class="w-full flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                            </div>
                            <p v-if="form.errors.slug" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <div>
                        <RichEditor
                            ref="editorRef"
                            v-model="form.content"
                            variant="full"
                            ai-assist
                            :ai-assist-actions="pageAiAssistActions"
                            :ai-assist-loading-key="aiLoading"
                            :ai-assist-label="t('AI Assist')"
                            :ai-assist-loading-label="t('Working...')"
                            @ai-assist="runAiAssist"
                        />
                        <p v-if="form.errors.content" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.content }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Excerpt (Short Summary)') }}</label>
                        <textarea v-model="form.excerpt" rows="3" :placeholder="t('Write a short summary for this page')" class="w-full resize-none rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        <p v-if="form.errors.excerpt" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.excerpt }}</p>
                    </div>
                </div>
                </div>

                <!-- SEO Card -->
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('SEO') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control how this page appears in search engines and social sharing previews.') }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Meta Title') }}</label>
                            <input v-model="form.meta_title" type="text" :placeholder="t('Enter SEO title')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="form.errors.meta_title" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.meta_title }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Meta Description') }}</label>
                            <textarea v-model="form.meta_description" rows="3" :placeholder="t('Enter meta description')" class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            <p v-if="form.errors.meta_description" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.meta_description }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Meta Keywords') }}</label>
                            <input v-model="form.meta_keywords" type="text" :placeholder="t('keyword1, keyword2...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="form.errors.meta_keywords" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.meta_keywords }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Open Graph Image') }}</label>
                            <div class="flex items-center gap-4">
                                <div class="flex h-20 w-32 items-center justify-center overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 dark:border-surface-800 dark:bg-surface-800">
                                    <img v-if="ogPreview" :src="ogPreview" class="h-full w-full object-cover">
                                    <svg v-else class="w-7 h-7 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:text-primary-300">
                                        <input type="file" class="hidden" @change="handleOgChange" accept="image/*">
                                        {{ t('Upload Image') }}
                                    </label>
                                    <button v-if="ogPreview" @click="removeOgImage" type="button" class="text-xs font-semibold text-red-500 hover:text-red-700 transition-colors flex items-center gap-1">
                                        <i class="ti ti-trash text-sm"></i>
                                        {{ t('Remove Image') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Column -->
            <div class="space-y-6">
                <!-- Page Attributes -->
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Attributes') }}</h3>
                    </div>
                    <div class="space-y-4">

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Parent Page') }}</label>
                            <AppSelect v-model="form.parent_id" :options="parentOptions" :placeholder="t('Select parent page')" />
                            <p v-if="form.errors.parent_id" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.parent_id }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Display Order') }}</label>
                            <input v-model="form.sort_order" type="number" :placeholder="t('Enter display order')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="form.errors.sort_order" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.sort_order }}</p>
                        </div>
                        <div v-if="form.status === 'scheduled'">
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Publish At') }}</label>
                            <input v-model="form.published_at" type="datetime-local" :placeholder="t('Choose publish date and time')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="form.errors.published_at" class="mt-2 text-xs font-bold text-danger-600">{{ form.errors.published_at }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Password Protection') }}</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="page?.has_password ? t('Leave blank to keep current password') : t('Optional page password')"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-11 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 inline-flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="showPassword ? t('Hide password') : t('Show password')"
                                    :title="showPassword ? t('Hide password') : t('Show password')"
                                    @click="showPassword = !showPassword"
                                >
                                    <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.password }}</p>
                            <p v-if="page?.has_password" class="mt-2 text-xs font-semibold text-primary-600 dark:text-primary-300">{{ t('Password enabled') }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-600 dark:text-gray-300">{{ t('Container Width') }}</label>
                            <AppSelect v-model="form.container_width" :options="containerWidthOptions" :placeholder="t('Select container width')" />
                            <p v-if="form.errors.container_width" class="mt-2 text-sm font-medium text-danger-600">{{ form.errors.container_width }}</p>
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Featured Image') }}</h3>
                    </div>
                    <div class="relative group">
                        <div class="aspect-video overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 flex items-center justify-center dark:border-surface-800 dark:bg-surface-800">
                            <img v-if="featuredPreview" :src="featuredPreview" class="w-full h-full object-cover">
                            <svg v-else class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <label class="absolute inset-0 flex items-center justify-center bg-gray-900/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer">
                            <input type="file" class="hidden" @change="handleFeaturedChange" accept="image/*">
                            <span class="text-white text-sm font-semibold">{{ t('Update Image') }}</span>
                        </label>
                    </div>
                    <button v-if="featuredPreview" @click="removeFeaturedImage" type="button" class="mt-4 text-xs font-semibold text-red-500 hover:text-red-700 transition-colors flex items-center gap-1">
                        <i class="ti ti-trash text-sm"></i>
                        {{ t('Remove Featured Image') }}
                    </button>
                </div>

                <!-- Layout Options -->
                <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Layout Options') }}</h3>
                    </div>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Title') }}</span>
                            <button @click="form.show_title = !form.show_title" type="button" :class="form.show_title ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_title ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label v-if="form.show_title" class="flex items-center justify-between cursor-pointer group pl-4 border-l-2 border-gray-100 dark:border-surface-800">
                            <span class="text-sm font-medium text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white">{{ t('Center Align Title') }}</span>
                            <button @click="form.center_title = !form.center_title" type="button" :class="form.center_title ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.center_title ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Breadcrumbs') }}</span>
                            <button @click="form.show_breadcrumbs = !form.show_breadcrumbs" type="button" :class="form.show_breadcrumbs ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_breadcrumbs ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Featured Image') }}</span>
                            <button @click="form.show_featured_image = !form.show_featured_image" type="button" :class="form.show_featured_image ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_featured_image ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Sidebar') }}</span>
                            <button @click="form.show_sidebar = !form.show_sidebar" type="button" :class="form.show_sidebar ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_sidebar ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <div v-if="form.show_sidebar" class="pt-2">
                            <AppSelect v-model="form.sidebar_position" :options="sidebarPositionOptions" :placeholder="t('Select sidebar position')" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ActionConfirmModal
        :open="confirmModal.open"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :confirm-label="confirmModal.confirmLabel"
        :processing-label="confirmModal.processingLabel"
        :processing="confirmModal.processing"
        :variant="confirmModal.variant"
        @cancel="closeConfirmModal"
        @confirm="runConfirmedAction"
    />
</template>
