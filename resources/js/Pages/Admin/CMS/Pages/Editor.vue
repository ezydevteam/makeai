<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import { useToastr } from '@/Composables/useToastr';
import { useTranslate } from '@/Composables/useTranslate';
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue';
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
    show_breadcrumbs: props.page?.show_breadcrumbs ?? true,
    show_featured_image: props.page?.show_featured_image ?? true,
    show_sidebar: props.page?.show_sidebar ?? false,
    sidebar_position: props.page?.sidebar_position ?? 'right',
    container_width: props.page?.container_width ?? 'default',
    featured_image: null as File | null,
    og_image: null as File | null,
});

const featuredPreview = ref(props.page?.featured_image ? `/storage/${props.page.featured_image}` : null);
const ogPreview = ref(props.page?.og_image ? `/storage/${props.page.og_image}` : null);

const statusOptions = computed(() => [
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
    { value: 'scheduled', label: t('Scheduled') },
]);

const templateOptions = computed(() => [
    { value: 'default', label: t('Default') },
    { value: 'full_width', label: t('Full Width') },
    { value: 'blank', label: t('Blank Canvas') },
    { value: 'landing', label: t('Landing Page') },
]);

const parentOptions = computed(() => [
    { value: null, label: t('None (Top Level)') },
    ...props.parents.map((parent) => ({
        value: parent.id,
        label: parent.title,
    })),
]);

const containerWidthOptions = computed(() => [
    { value: 'default', label: t('Default') },
    { value: 'narrow', label: t('Narrow') },
    { value: 'wide', label: t('Wide') },
    { value: 'full', label: t('Full') },
]);

const sidebarPositionOptions = computed(() => [
    { value: 'left', label: t('Sidebar Left') },
    { value: 'right', label: t('Sidebar Right') },
]);

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
    }
};

const handleOgChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.og_image = file;
        ogPreview.value = URL.createObjectURL(file);
    }
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
</script>

<template>
    <Head :title="page ? t('Edit Page') : t('Create Page')" />
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex items-start gap-4">
                    <Link :href="route('admin.pages.index')" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 text-gray-500 transition-colors hover:bg-white hover:text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 dark:hover:text-white">
                        <i class="ti ti-arrow-left text-lg"></i>
                    </Link>
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:border-primary-900/30 dark:bg-primary-900/20 dark:text-primary-300">
                            <i class="ti ti-file-text text-sm"></i>
                            {{ page ? t('Content Editor') : t('New Content Draft') }}
                        </div>
                        <h1 class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ page ? t('Edit Page') : t('Create Page') }}</h1>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ t('Design, organize, and publish custom content for your public site from one workspace.') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button
                    v-if="page && !page.is_system"
                    @click="confirmDeletePage"
                    type="button"
                    class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-xs font-bold uppercase tracking-widest text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30"
                >
                    {{ t('Delete Page') }}
                </button>

                    <div class="min-w-[180px]">
                        <AppSelect v-model="form.status" :options="statusOptions" />
                    </div>

                    <button @click="submit" :disabled="form.processing" class="btn-primary rounded-2xl px-8 py-3 font-bold transition-all shadow-lg shadow-primary-600/20">
                        {{ form.processing ? t('Saving...') : page ? t('Update Page') : t('Publish Page') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Editor Column -->
            <div class="space-y-6">
                <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="space-y-6">
                    <div>
                        <label class="mb-3 block text-xs font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ t('Page Title') }}</label>
                        <input v-model="form.title" @input="syncSlug" type="text" :placeholder="t('Enter page title')" class="w-full border-none bg-transparent p-0 text-4xl font-black text-gray-900 placeholder:text-gray-300 focus:ring-0 dark:text-white dark:placeholder:text-gray-600">
                        <div class="mt-4 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-800">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Page Slug') }}</label>
                            <div class="flex flex-col gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 md:flex-row md:items-center">
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $page.props.app?.url }}/</span>
                                <input v-model="form.slug" @input="markSlugTouched" type="text" :placeholder="t('page-slug')" class="min-w-[220px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-900 dark:text-white">
                            </div>
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
                    </div>

                    <div>
                        <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Excerpt (Short Summary)') }}</label>
                        <textarea v-model="form.excerpt" rows="3" class="w-full resize-none rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </div>
                </div>
                </div>

                <!-- SEO Card -->
                <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-8">
                        <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-900 dark:text-white">{{ t('Search Engine Optimization') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how this page appears in search engines and social sharing previews.') }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Meta Title') }}</label>
                            <input v-model="form.meta_title" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Meta Description') }}</label>
                            <textarea v-model="form.meta_description" rows="3" class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Meta Keywords') }}</label>
                            <input v-model="form.meta_keywords" type="text" :placeholder="t('keyword1, keyword2...')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-all focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Open Graph Image') }}</label>
                            <div class="flex items-center gap-4">
                                <div class="flex h-20 w-32 items-center justify-center overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 dark:border-surface-800 dark:bg-surface-800">
                                    <img v-if="ogPreview" :src="ogPreview" class="h-full w-full object-cover">
                                    <svg v-else class="w-7 h-7 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                                <label class="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-xs font-bold text-gray-600 transition-colors hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:text-primary-300">
                                    <input type="file" class="hidden" @change="handleOgChange" accept="image/*">
                                    {{ t('Upload Image') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Column -->
            <div class="space-y-6">
                <!-- Page Attributes -->
                <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-6">
                        <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-900 dark:text-white">{{ t('Attributes') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Control template choice, structure, visibility, and layout behavior.') }}</p>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Template') }}</label>
                            <AppSelect v-model="form.template" :options="templateOptions" />
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Parent Page') }}</label>
                            <AppSelect v-model="form.parent_id" :options="parentOptions" />
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Display Order') }}</label>
                            <input v-model="form.sort_order" type="number" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                        <div v-if="form.status === 'scheduled'">
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Publish At') }}</label>
                            <input v-model="form.published_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="form.errors.published_at" class="mt-2 text-xs font-bold text-danger-600">{{ form.errors.published_at }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Password Protection') }}</label>
                            <input v-model="form.password" type="password" :placeholder="page?.has_password ? t('Leave blank to keep current password') : t('Optional page password')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-0 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <p v-if="page?.has_password" class="mt-2 text-[10px] font-bold uppercase tracking-widest text-primary-600 dark:text-primary-300">{{ t('Password enabled') }}</p>
                        </div>
                        <div>
                            <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ t('Container Width') }}</label>
                            <AppSelect v-model="form.container_width" :options="containerWidthOptions" />
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-4">
                        <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-900 dark:text-white">{{ t('Featured Image') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose the main visual used for listings and page presentation.') }}</p>
                    </div>
                    <div class="relative group">
                        <div class="aspect-video overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 flex items-center justify-center dark:border-surface-800 dark:bg-surface-800">
                            <img v-if="featuredPreview" :src="featuredPreview" class="w-full h-full object-cover">
                            <svg v-else class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <label class="absolute inset-0 flex items-center justify-center bg-gray-900/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer">
                            <input type="file" class="hidden" @change="handleFeaturedChange" accept="image/*">
                            <span class="text-white text-[10px] font-black uppercase tracking-widest">{{ t('Update Image') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Layout Options -->
                <div class="rounded-3xl border border-gray-100 bg-white p-8 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-6">
                        <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-900 dark:text-white">{{ t('Layout Options') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Toggle visible elements and adjust how this page is framed on the frontend.') }}</p>
                    </div>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Title') }}</span>
                            <button @click="form.show_title = !form.show_title" type="button" :class="form.show_title ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_title ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-gray-600 group-hover:text-gray-900 dark:text-gray-300 dark:group-hover:text-white">{{ t('Show Sidebar') }}</span>
                            <button @click="form.show_sidebar = !form.show_sidebar" type="button" :class="form.show_sidebar ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_sidebar ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <div v-if="form.show_sidebar" class="pt-2">
                            <AppSelect v-model="form.sidebar_position" :options="sidebarPositionOptions" />
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
